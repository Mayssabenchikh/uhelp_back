<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    public function index()
    {
        return Group::with('users','owner')->paginate(20);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'=>'required|string|max:120',
            'description'=>'nullable|string',
            'members'=>'nullable|array',
            'members.*'=>'exists:users,id'
        ]);

        $group = Group::create([
            'name'=>$request->name,
            'description'=>$request->description,
            'owner_id'=>Auth::id()
        ]);

        if ($request->filled('members')) {
            $members = collect($request->members)->mapWithKeys(fn($id)=>[$id=>['role'=>'member']])->toArray();
            $group->users()->attach($members);
        }

        // attach owner as admin
        $group->users()->syncWithoutDetaching([Auth::id()=>['role'=>'admin']]);

        return response()->json($group->load('users','owner'), 201);
    }

    public function show(Group $group)
    {
        $this->authorize('view', $group);
        return $group->load('users','owner','conversations');
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('update', $group);
        $request->validate(['name'=>'required|string|max:120','description'=>'nullable|string','members'=>'nullable|array','members.*'=>'exists:users,id']);
        $group->update($request->only('name','description'));
        if ($request->filled('members')) {
            $members = collect($request->members)->mapWithKeys(fn($id)=>[$id=>['role'=>'member']])->toArray();
            $group->users()->sync($members);
            $group->users()->syncWithoutDetaching([$group->owner_id=>['role'=>'admin']]);
        }
        return response()->json($group->load('users','owner'));
    }

    public function destroy(Group $group)
    {
        $this->authorize('delete', $group);
        $group->delete();
        return response()->json(['ok'=>true]);
    }

    public function addMember(Request $r, Group $group)
    {
        $this->authorize('update', $group);
        $r->validate(['user_id'=>'required|exists:users,id','role'=>'nullable|string']);
        $group->users()->attach($r->user_id, ['role'=>$r->role ?? 'member']);
        return response()->json($group->load('users'));
    }

    public function removeMember(Request $r, Group $group)
    {
        $this->authorize('update', $group);
        $r->validate(['user_id'=>'required|exists:users,id']);
        $group->users()->detach($r->user_id);
        return response()->json(['ok'=>true]);
    }
}
