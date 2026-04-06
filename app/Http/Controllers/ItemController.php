<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with(['user:id,name,neighborhood_area', 'category'])
            ->where('is_available', true)
            ->when($request->category, fn($q, $c) => $q->whereHas('category', fn($q2) => $q2->where('slug', $c)))
            ->latest()
            ->paginate(12);

        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();

        return view('items.index', compact('items', 'categories'));
    }

    public function create()
    {
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'condition' => 'required|in:excellent,good,fair,poor',
            'credit_type' => 'required|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'image|mimes:jpeg,png,webp|max:5120',
        ]);

        $data['user_id'] = $request->user()->id;
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
        return view('items.show', compact('item'));
    }

    public function edit(Item $item)
    {
        $this->authorize('update', $item);
        $categories = Category::whereIn('type', ['item', 'both'])->orderBy('name')->get();
        return view('items.edit', compact('item', 'categories'));
    }

    public function update(Request $request, Item $item)
    {
        $this->authorize('update', $item);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'condition' => 'required|in:excellent,good,fair,poor',
            'credit_type' => 'required|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'is_available' => 'boolean',
        ]);

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
