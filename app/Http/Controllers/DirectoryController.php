<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class DirectoryController extends Controller
{
    public function index()
    {
        $groups = Organization::where('active', true)
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        $labels = [
            'community'  => 'Community',
            'services'   => 'Services',
            'business'   => 'Business',
            'government' => 'Government',
        ];

        return view('directory.index', compact('groups', 'labels'));
    }
}
