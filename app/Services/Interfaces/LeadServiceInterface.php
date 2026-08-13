<?php

namespace App\Services\Interfaces;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadNote;
use App\Models\LeadStage;
use Illuminate\Database\Eloquent\Collection;

interface LeadServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Lead;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Lead $lead, array $data): Lead;

    public function assign(Lead $lead, int $userId): Lead;

    public function changeStatus(Lead $lead, string $status): Lead;

    /**
     * @return array<string, mixed>
     */
    public function convertToCustomer(Lead $lead): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function addActivity(Lead $lead, array $data): void;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array;

    /**
     * @return array<string, mixed>
     */
    public function getPipeline(): array;

    /**
     * @return Collection<int, LeadStage>
     */
    public function getStages(): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createStage(array $data): LeadStage;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStage(LeadStage $stage, array $data): LeadStage;

    public function deleteStage(LeadStage $stage): bool;

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNote(Lead $lead, array $data): LeadNote;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateNote(LeadNote $note, array $data): LeadNote;

    public function deleteNote(LeadNote $note): bool;

    /**
     * @param  array<int, int>  $leadIds
     */
    public function bulkAssign(array $leadIds, int $userId): int;

    /**
     * @return Collection<int, LeadActivity>
     */
    public function getReminders(?int $userId = null): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function createReminder(Lead $lead, array $data): LeadActivity;

    public function completeReminder(LeadActivity $activity): LeadActivity;
}
