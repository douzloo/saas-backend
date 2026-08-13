<?php

namespace App\Services\Interfaces;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface TicketServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Ticket;

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function addMessage(Ticket $ticket, string $body, User $user, bool $isInternal = false, array $attachments = []): void;

    public function assign(Ticket $ticket, int $userId): Ticket;

    public function changeStatus(Ticket $ticket, string $status): Ticket;

    public function changePriority(Ticket $ticket, string $priority): Ticket;

    public function close(Ticket $ticket): Ticket;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array;
}
