<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Contracts\View\View;

class EventListingController extends Controller
{
    public function index(): View
    {
        $events = Event::query()
            ->published()
            ->where('ends_at', '>=', now())
            ->with(['ticketTypes' => fn ($q) => $q->where('is_active', true)])
            ->withCount('tickets')
            ->orderBy('starts_at')
            ->paginate(12);

        return view('public.events.index', compact('events'));
    }

    public function show(Event $event): View
    {
        abort_unless(
            $event->status === EventStatus::Published || $event->status === EventStatus::Closed,
            404
        );

        $event->load([
            'ticketTypes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ]);

        return view('public.events.show', compact('event'));
    }
}