<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QuickResponse;
use Illuminate\Http\Request;

class QuickResponseController extends Controller
{
    public function index()
    {
        return QuickResponse::orderBy('created_at', 'desc')->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'language' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $qr = QuickResponse::create($data);
        return response()->json($qr, 201);
    }

    public function show(QuickResponse $quickResponse)
    {
        return $quickResponse;
    }

    public function update(Request $request, QuickResponse $quickResponse)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'language' => 'nullable|string|max:10',
            'category' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $quickResponse->update($data);
        return response()->json($quickResponse);
    }

    public function destroy(QuickResponse $quickResponse)
    {
        $quickResponse->delete();
        return response()->json(null, 204);
    }
}
