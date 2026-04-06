<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request)
    {
        $skills = Skill::with(['user:id,name,neighborhood_area', 'category'])
            ->where('is_available', true)
            ->when($request->category, fn($q, $c) => $q->whereHas('category', fn($q2) => $q2->where('slug', $c)))
            ->latest()
            ->paginate(12);

        $categories = Category::whereIn('type', ['skill', 'both'])->orderBy('name')->get();

        return view('skills.index', compact('skills', 'categories'));
    }

    public function create()
    {
        $categories = Category::whereIn('type', ['skill', 'both'])->orderBy('name')->get();
        return view('skills.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'credit_type' => 'required|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
        ]);

        $data['user_id'] = $request->user()->id;
        $skill = Skill::create($data);

        return redirect()->route('skills.show', $skill)->with('success', 'Skill listed successfully.');
    }

    public function show(Skill $skill)
    {
        $skill->load(['user:id,name,avatar,neighborhood_area,bio', 'category']);
        return view('skills.show', compact('skill'));
    }

    public function edit(Skill $skill)
    {
        $this->authorize('update', $skill);
        $categories = Category::whereIn('type', ['skill', 'both'])->orderBy('name')->get();
        return view('skills.edit', compact('skill', 'categories'));
    }

    public function update(Request $request, Skill $skill)
    {
        $this->authorize('update', $skill);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category_id' => 'required|exists:categories,id',
            'credit_type' => 'required|in:gift,time_equal,custom',
            'custom_credit_value' => 'required_if:credit_type,custom|nullable|numeric|min:0',
            'is_available' => 'boolean',
        ]);

        $skill->update($data);

        return redirect()->route('skills.show', $skill)->with('success', 'Skill updated.');
    }

    public function destroy(Skill $skill)
    {
        $this->authorize('delete', $skill);
        $skill->delete();
        return redirect()->route('skills.index')->with('success', 'Skill removed.');
    }
}
