<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Organization;
use App\Models\Source;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index()
    {
        $sources = Source::orderBy('name')->paginate(30);
        return view('admin.sources.index', compact('sources'));
    }

    public function create()
    {
        return view('admin.sources.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $source = Source::create($data);

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'source_create',
            'payload'    => ['source_id' => $source->id, 'name' => $source->name],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.sources.index')->with('success', 'Source created.');
    }

    public function edit(Source $source)
    {
        return view('admin.sources.edit', compact('source'));
    }

    public function update(Request $request, Source $source)
    {
        $source->update($this->validated($request));

        return redirect()->route('admin.sources.index')->with('success', 'Source updated.');
    }

    public function destroy(Request $request, Source $source)
    {
        $payload = ['source_id' => $source->id, 'name' => $source->name];
        $source->delete();

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'source_delete',
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Source deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url:http,https|max:2048',
            'type' => 'required|in:' . implode(',', Source::TYPES),
            'selector_config' => 'nullable|string|json',
            'organization_id' => 'nullable|exists:organizations,id',
            'active' => 'boolean',
        ]);

        $data['selector_config'] = filled($data['selector_config'] ?? null)
            ? json_decode($data['selector_config'], true)
            : null;
        $data['active'] = $request->boolean('active');

        return $data;
    }
}
