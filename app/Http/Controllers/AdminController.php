<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Post;
use App\Models\Professor;
use App\Models\Zone;
use App\Models\Report;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'users_count' => User::count(),
            'posts_count' => Post::count(),
            'professors_count' => Professor::count(),
            'zones_count' => Zone::count(),
            'reports_pending' => Report::where('status', 'pending')->count(),
        ];

        $recentPosts = Post::with('user')->orderBy('created_at', 'desc')->take(5)->get();
        $recentReports = Report::with('reporter')->orderBy('created_at', 'desc')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentPosts', 'recentReports'));
    }

    public function reports()
    {
        $reports = Report::with('reporter')->orderBy('created_at', 'desc')->paginate(20);
        return view('admin.reports', compact('reports'));
    }

    public function resolveReport($id, Request $request)
    {
        $report = Report::findOrFail($id);
        $action = $request->input('action', 'resolved');

        $report->status = $action === 'reject' ? 'rejected' : 'resolved';
        $report->action_taken = $request->input('action_taken', 'Admin tarafından incelendi');
        $report->save();

        return redirect()->back()->with('success', 'Rapor durumu güncellendi.');
    }

    public function professors()
    {
        $professors = Professor::withCount('ratings')->orderBy('name', 'asc')->paginate(20);
        return view('admin.professors', compact('professors'));
    }

    public function storeProfessor(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'department' => 'required|string',
        ]);

        Professor::create($request->only('name', 'department'));
        return redirect()->back()->with('success', 'Hoca başarıyla eklendi.');
    }

    public function zones()
    {
        $zones = Zone::withCount('votes')->orderBy('name', 'asc')->get();
        return view('admin.zones', compact('zones'));
    }
}
