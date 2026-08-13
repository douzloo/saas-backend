<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function submit(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'product_id' => 'nullable|exists:products,id',
            'type' => 'nullable|in:contact,support,sales,demo',
        ]);

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'company' => $request->company,
            'product_id' => $request->product_id,
            'source' => 'website',
            'status' => 'new',
            'priority' => $request->type === 'sales' ? 'high' : 'medium',
            'notes' => "موضوع: {$request->subject}\n\n{$request->message}",
            'metadata' => [
                'type' => $request->type,
                'subject' => $request->subject,
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);

        $adminEmail = Setting::get('contact_email', 'info@douzloo.com');

        return response()->json([
            'message' => 'پیام شما با موفقیت ارسال شد. به زودی با شما تماس خواهیم گرفت.',
            'lead_id' => $lead->id,
        ], 201);
    }

    public function demo(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:20',
            'company' => 'required|string|max:255',
            'employees_count' => 'nullable|string',
            'current_software' => 'nullable|string',
            'message' => 'nullable|string|max:2000',
        ]);

        $lead = Lead::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'company' => $request->company,
            'source' => 'website',
            'status' => 'new',
            'priority' => 'high',
            'estimated_value' => 5000000,
            'notes' => $request->message,
            'metadata' => [
                'type' => 'demo_request',
                'employees_count' => $request->employees_count,
                'current_software' => $request->current_software,
            ],
        ]);

        return response()->json([
            'message' => 'درخواست دمو شما ثبت شد. تیم فروش به زودی با شما تماس خواهد گرفت.',
            'lead_id' => $lead->id,
        ], 201);
    }
}
