<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // Liste tous les départements
    public function index()
    {
        return response()->json(Department::all());
    }

    // Crée un nouveau département
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name|max:255',
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'Department created successfully',
            'department' => $department
        ], 201);
    }

    // Affiche un département
    public function show(Department $department)
    {
        return response()->json($department);
    }

    // Met à jour un département
    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name,'.$department->id.'|max:255',
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'department' => $department
        ]);
    }

    // Supprime un département
    public function destroy(Department $department)
    {
        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully'
        ]);
    }
}
