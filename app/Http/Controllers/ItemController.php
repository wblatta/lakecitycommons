<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Models\Category;
use App\Models\ExchangeRequest;
use App\Models\Item;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistAvailable;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['user:id,name,neighborhood_area', 'category'])
            ->where('is_available', true)
            ->where('is_archived', false)
            ->when($request->category, fn($q, $c) => $q->whereHas('category', fn($q2) => $q2->where('slug', $c)))
            ->latest()
            ->paginate(12);

        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();

        $archivedItems = auth()->check()
            ? Item::with('category')
                ->where('user_id', auth()->id())
                ->where('is_archived', true)
                ->latest()
                ->limit(20)
                ->get()
            : collect();

        return view('items.index', compact('items', 'categories', 'archivedItems'));
    }

    public function create()
    {
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.create', compact('categories'));
    }

    public function store(StoreItemRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if ($data['offer_type'] === 'gift') {
            $data['credit_type'] = 'gift';
            $data['custom_credit_value'] = null;
        }

        $item = Item::create($data);

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $item->addMedia($photo)->toMediaCollection('photos');
            }
        }

        return redirect()->route('items.show', $item)->with('success', 'Item listed successfully.');
    }

    public function show(Item $item)
    {
        $item->load(['user:id,name,avatar,neighborhood_area,bio', 'category']);

        $onWaitlist = auth()->check()
            ? WaitlistEntry::where('user_id', auth()->id())
                ->where('resource_type', 'item')
                ->where('resource_id', $item->id)
                ->exists()
            : false;

        $waitlistCount = WaitlistEntry::where('resource_type', 'item')
            ->where('resource_id', $item->id)
            ->count();

        return view('items.show', compact('item', 'onWaitlist', 'waitlistCount'));
    }

    public function toggle(Item $item)
    {
        $this->authorize('update', $item);

        // Block toggle while item is lent out: 'completed' means borrower has it but
        // hasn't been marked returned yet — availability is restored by RequestService::transition.
        $activeLend = ExchangeRequest::where('resource_type', 'item')
            ->where('resource_id', $item->id)
            ->whereIn('status', ['in_progress', 'completed'])
            ->exists();

        if ($activeLend) {
            return back()->with('error', 'This item is currently lent out and cannot be toggled until it is returned.');
        }

        $item->update(['is_available' => !$item->is_available]);

        if ($item->is_available) {
            $entries = WaitlistEntry::where('resource_type', 'item')
                ->where('resource_id', $item->id)
                ->whereNull('notified_at')
                ->with('user')
                ->get();

            foreach ($entries as $entry) {
                $entry->user->notify(new WaitlistAvailable($item->title, route('items.show', $item)));
                $entry->update(['notified_at' => now()]);
            }
        }

        $message = $item->is_available ? 'Item is now available.' : 'Item placed on hold.';
        return back()->with('success', $message);
    }

    public function edit(Item $item)
    {
        $this->authorize('update', $item);
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.edit', compact('item', 'categories'));
    }

    public function update(UpdateItemRequest $request, Item $item)
    {
        $this->authorize('update', $item);

        $data = $request->validated();

        if ($data['offer_type'] === 'gift') {
            $data['credit_type'] = 'gift';
            $data['custom_credit_value'] = null;
        }

        $item->update($data);

        return redirect()->route('items.show', $item)->with('success', 'Item updated.');
    }

    public function destroy(Item $item)
    {
        $this->authorize('delete', $item);
        $item->clearMediaCollection('photos');
        $item->delete();
        return redirect()->route('items.index')->with('success', 'Item removed.');
    }
}
