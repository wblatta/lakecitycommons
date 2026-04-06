<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Skill;
use App\Models\WaitlistEntry;
use App\Notifications\WaitlistAvailable;
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

        $onWaitlist = auth()->check()
            ? WaitlistEntry::where('user_id', auth()->id())
                ->where('resource_type', 'skill')
                ->where('resource_id', $skill->id)
                ->exists()
            : false;

        $waitlistCount = WaitlistEntry::where('resource_type', 'skill')
            ->where('resource_id', $skill->id)
            ->count();

        return view('skills.show', compact('skill', 'onWaitlist', 'waitlistCount'));
    }

    public function toggle(Skill $skill)
    {
        $this->authorize('update', $skill);
        $skill->update(['is_available' => !$skill->is_available]);

        if ($skill->is_available) {
            $entries = WaitlistEntry::where('resource_type', 'skill')
                ->where('resource_id', $skill->id)
                ->whereNull('notified_at')
                ->with('user')
                ->get();

            foreach ($entries as $entry) {
                $entry->user->notify(new WaitlistAvailable($skill->title, route('skills.show', $skill)));
                $entry->update(['notified_at' => now()]);
            }
        }

        $message = $skill->is_available ? 'Skill is now available.' : 'Skill placed on hold.';
        return back()->with('success', $message);
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
