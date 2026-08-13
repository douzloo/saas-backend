<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\LeadStage;
use App\Models\User;
use App\Services\Interfaces\LeadServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LeadService implements LeadServiceInterface
{
    public function create(array $data): Lead
    {
        $lead = Lead::create($data);

        if (! empty($data['tags'])) {
            $lead->tags()->sync($data['tags']);
        }

        $this->addActivity($lead, [
            'type' => 'system',
            'subject' => 'Lead created',
            'description' => 'New lead "'.$lead->name.'" was created from '.$lead->source,
        ]);

        return $lead->load('tags', 'assignee', 'product');
    }

    public function update(Lead $lead, array $data): Lead
    {
        $oldStatus = $lead->status;
        $lead->update($data);

        if (! empty($data['tags'])) {
            $lead->tags()->sync($data['tags']);
        }

        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $this->addActivity($lead, [
                'type' => 'status_change',
                'subject' => 'Status changed',
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => $data['status']],
            ]);
        }

        return $lead->fresh(['tags', 'assignee', 'product']);
    }

    public function assign(Lead $lead, int $userId): Lead
    {
        $lead->update(['assigned_to' => $userId]);

        $this->addActivity($lead, [
            'type' => 'system',
            'subject' => 'Lead assigned',
            'description' => 'Lead assigned to '.User::find($userId)->name,
        ]);

        return $lead->fresh('assignee');
    }

    public function changeStatus(Lead $lead, string $status): Lead
    {
        $oldStatus = $lead->status;
        $data = ['status' => $status];

        $timestampField = match ($status) {
            'contacted' => 'contacted_at',
            'qualified' => 'qualified_at',
            default => null,
        };

        if ($timestampField) {
            $data[$timestampField] = now();
        }

        $lead->update($data);

        $this->addActivity($lead, [
            'type' => 'status_change',
            'subject' => 'Status changed from '.$oldStatus.' to '.$status,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $status],
        ]);

        return $lead->fresh();
    }

    public function convertToCustomer(Lead $lead): array
    {
        return DB::transaction(function () use ($lead) {
            $existing = User::where('email', $lead->email)->first();

            $user = $existing ?? User::create([
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'role' => 'customer',
                'status' => 'active',
                'password' => bcrypt('password'),
            ]);

            Contact::updateOrCreate(
                ['lead_id' => $lead->id],
                [
                    'user_id' => $user->id,
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'company' => $lead->company,
                    'job_title' => $lead->job_title,
                ]
            );

            $lead->update([
                'status' => 'won',
                'converted_at' => now(),
                'converted_user_id' => $user->id,
            ]);

            $this->addActivity($lead, [
                'type' => 'system',
                'subject' => 'Lead converted',
                'description' => 'Lead converted to customer (User ID: '.$user->id.')',
            ]);

            return ['user' => $user, 'lead' => $lead->fresh()];
        });
    }

    public function addActivity(Lead $lead, array $data): void
    {
        $lead->activities()->create(array_merge(
            $data,
            ['user_id' => auth()->id()]
        ));
    }

    public function getStats(): array
    {
        return [
            'total' => Lead::count(),
            'new' => Lead::where('status', 'new')->count(),
            'contacted' => Lead::where('status', 'contacted')->count(),
            'qualified' => Lead::where('status', 'qualified')->count(),
            'proposal' => Lead::where('status', 'proposal')->count(),
            'negotiation' => Lead::where('status', 'negotiation')->count(),
            'won' => Lead::where('status', 'won')->count(),
            'lost' => Lead::where('status', 'lost')->count(),
            'total_value' => Lead::sum('estimated_value'),
            'won_value' => Lead::where('status', 'won')->sum('estimated_value'),
            'by_source' => Lead::select('source', DB::raw('count(*) as count'))
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
        ];
    }

    public function getPipeline(): array
    {
        $stages = LeadStage::active()->ordered()->get();

        $groups = $stages->map(function (LeadStage $stage) {
            $leads = Lead::with(['assignee', 'product', 'tags'])
                ->where('status', $stage->key)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get();

            return [
                'id' => $stage->id,
                'name' => $stage->name,
                'key' => $stage->key,
                'color' => $stage->color,
                'sort_order' => $stage->sort_order,
                'count' => $leads->count(),
                'total_value' => (int) $leads->sum('estimated_value'),
                'leads' => $leads,
            ];
        });

        $unmappedCounts = Lead::whereNotIn('status', $stages->pluck('key')->all())
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'stages' => $groups->values()->all(),
            'unmapped' => $unmappedCounts,
        ];
    }

    public function getStages(): Collection
    {
        return LeadStage::ordered()->get();
    }

    public function createStage(array $data): LeadStage
    {
        return LeadStage::create($data);
    }

    public function updateStage(LeadStage $stage, array $data): LeadStage
    {
        $stage->update($data);

        return $stage->fresh();
    }

    public function deleteStage(LeadStage $stage): bool
    {
        return (bool) $stage->delete();
    }

    public function addNote(Lead $lead, array $data): LeadNote
    {
        return $lead->notes()->create(array_merge($data, [
            'user_id' => auth()->id(),
        ]));
    }

    public function updateNote(LeadNote $note, array $data): LeadNote
    {
        $note->update($data);

        return $note->fresh();
    }

    public function deleteNote(LeadNote $note): bool
    {
        return (bool) $note->delete();
    }

    public function bulkAssign(array $leadIds, int $userId): int
    {
        $count = 0;

        foreach ($leadIds as $leadId) {
            $lead = Lead::find($leadId);

            if ($lead === null || (int) $lead->assigned_to === $userId) {
                continue;
            }

            $lead->update(['assigned_to' => $userId]);

            $this->addActivity($lead, [
                'type' => 'system',
                'subject' => 'Lead assigned',
                'description' => 'Lead assigned to '.User::find($userId)?->name,
            ]);

            $count++;
        }

        return $count;
    }

    public function getReminders(?int $userId = null): Collection
    {
        $query = LeadActivity::query()
            ->where('type', 'task')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '>=', now());
            })
            ->where('is_completed', false)
            ->with('lead', 'user')
            ->orderBy('scheduled_at');

        if ($userId !== null) {
            $query->whereHas('lead', fn ($q) => $q->where('assigned_to', $userId));
        }

        return $query->limit(50)->get();
    }

    public function createReminder(Lead $lead, array $data): LeadActivity
    {
        return $lead->activities()->create(array_merge($data, [
            'type' => 'task',
            'is_completed' => false,
            'user_id' => auth()->id(),
        ]));
    }

    public function completeReminder(LeadActivity $activity): LeadActivity
    {
        $activity->update([
            'is_completed' => true,
            'scheduled_at' => $activity->scheduled_at ?? now(),
        ]);

        return $activity->fresh();
    }
}
