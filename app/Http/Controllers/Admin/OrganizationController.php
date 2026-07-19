<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::orderBy('name')->paginate(30);
        return view('admin.organizations.index', compact('organizations'));
    }

    public function create()
    {
        return view('admin.organizations.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $org = Organization::create($data);
        $this->attachLogo($request, $org);

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'organization_create',
            'payload'    => ['organization_id' => $org->id, 'name' => $org->name],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization created.');
    }

    public function edit(Organization $organization)
    {
        return view('admin.organizations.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization)
    {
        $organization->update($this->validated($request));
        $this->attachLogo($request, $organization);

        return redirect()->route('admin.organizations.index')->with('success', 'Organization updated.');
    }

    public function destroy(Request $request, Organization $organization)
    {
        $payload = ['organization_id' => $organization->id, 'name' => $organization->name];
        $organization->delete();

        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => 'organization_delete',
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Organization deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'category'     => 'required|in:' . implode(',', Organization::CATEGORIES),
            'description'  => 'nullable|string|max:5000',
            'website'      => 'nullable|url|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:40',
            'address'      => 'nullable|string|max:255',
            'is_sponsor'   => 'boolean',
            'sponsor_tier' => 'nullable|string|max:60',
            'active'       => 'boolean',
            'logo'         => 'nullable|image|max:2048',
        ]);

        $data['is_sponsor'] = $request->boolean('is_sponsor');
        $data['active'] = $request->boolean('active');
        unset($data['logo']);

        return $data;
    }

    private function attachLogo(Request $request, Organization $org): void
    {
        if ($request->hasFile('logo')) {
            $org->addMediaFromRequest('logo')->toMediaCollection('logo');
        }
    }
}
