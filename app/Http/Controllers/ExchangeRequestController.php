<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\Skill;
use App\Services\CreditService;
use App\Services\MessageService;
use App\Services\RequestService;
use Illuminate\Http\Request;

class ExchangeRequestController extends Controller
{
    public function __construct(
        private RequestService $requestService,
        private CreditService $creditService,
        private MessageService $messageService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $sent = $user->sentRequests()->with(['owner:id,name'])->latest()->get();
        $received = $user->receivedRequests()->with(['requester:id,name'])->latest()->get();

        return view('requests.index', compact('sent', 'received'));
    }

    public function create(Request $request)
    {
        $resourceType = $request->query('type', 'skill');
        $resourceId = $request->query('id');

        $resource = $resourceType === 'skill'
            ? Skill::findOrFail($resourceId)
            : Item::findOrFail($resourceId);

        return view('requests.create', compact('resource', 'resourceType'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'resource_type' => 'required|in:skill,item',
            'resource_id' => 'required|integer',
            'proposed_datetime' => 'required|date|after:now',
            'duration_hours' => 'nullable|numeric|min:0.25|max:24',
            'message' => 'nullable|string|max:1000',
        ]);

        $resource = $data['resource_type'] === 'skill'
            ? Skill::findOrFail($data['resource_id'])
            : Item::findOrFail($data['resource_id']);

        $creditValue = $this->creditService->calculateCreditValue(
            $resource->credit_type,
            $resource->custom_credit_value,
            (float) ($data['duration_hours'] ?? 1.0)
        );

        $exchangeRequest = ExchangeRequest::create([
            'requester_id' => $request->user()->id,
            'owner_id' => $resource->user_id,
            'resource_type' => $data['resource_type'],
            'resource_id' => $data['resource_id'],
            'proposed_datetime' => $data['proposed_datetime'],
            'duration_hours' => $data['duration_hours'] ?? null,
            'message' => $data['message'] ?? null,
            'credit_type' => $resource->credit_type,
            'credit_value' => $creditValue,
        ]);

        $this->messageService->createThreadForRequest($exchangeRequest, $data['message'] ?? '');

        return redirect()->route('requests.index')->with('success', 'Request sent.');
    }

    public function show(ExchangeRequest $request)
    {
        $this->authorize('view', $request);
        $request->load(['requester:id,name', 'owner:id,name', 'thread.messages.sender:id,name,avatar']);
        return view('requests.show', ['exchangeRequest' => $request]);
    }

    public function confirm(Request $request, ExchangeRequest $exchangeRequest)
    {
        $this->authorize('view', $exchangeRequest);
        $this->requestService->confirmCompletion($exchangeRequest, $request->user(), $this->creditService);
        return back()->with('success', 'Confirmation recorded.');
    }

    public function transition(Request $request, ExchangeRequest $exchangeRequest)
    {
        $this->authorize('view', $exchangeRequest);
        $request->validate(['status' => 'required|string']);
        $this->requestService->transition($exchangeRequest, $request->status, $request->user());
        return back()->with('success', 'Status updated.');
    }
}
