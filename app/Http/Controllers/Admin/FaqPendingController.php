<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PendingFaq;
use App\Models\Faq;
use Illuminate\Support\Facades\Auth;

class FaqPendingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum','role:admin']);
    }

    public function index(Request $r)
    {
        $perPage = (int) $r->get('per_page', 20);
        $query = PendingFaq::query();

        if ($r->filled('status')) $query->where('status', $r->get('status'));
        if ($r->filled('language')) $query->where('language', $r->get('language'));

        $p = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return response()->json($p);
    }

    public function show($id)
    {
        $item = PendingFaq::findOrFail($id);
        return response()->json($item);
    }

    public function approve(Request $r, $id)
    {
        $item = PendingFaq::findOrFail($id);
        if ($item->status !== 'pending') {
            return response()->json(['message' => 'Not pending'], 422);
        }

        $faq = Faq::create([
            'question' => $item->question,
            'answer' => $item->answer ?? '',
            'language' => $item->language ?? 'en',
            'category' => $item->category,
            'is_active' => true,
            'user_id' => Auth::id()
        ]);

        $item->status = 'approved';
        $item->save();

        return response()->json(['ok' => true, 'faq' => $faq]);
    }

    public function reject(Request $r, $id)
    {
        $item = PendingFaq::findOrFail($id);
        if ($item->status !== 'pending') {
            return response()->json(['message' => 'Not pending'], 422);
        }

        $item->status = 'rejected';
        $item->save();

        // Optionally delete record
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function destroy($id)
    {
        $item = PendingFaq::findOrFail($id);
        $item->delete();
        return response()->json(['ok' => true]);
    }
}
