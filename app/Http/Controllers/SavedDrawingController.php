<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedDrawing;
use Illuminate\Support\Facades\Auth;

class SavedDrawingController extends Controller
{
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Not logged in'], 401);
        }

        SavedDrawing::create([
            'user_id' => Auth::id(),
            'image_path' => session('uploadedLevel'),
        ]);

        return response()->json(['success' => true]);
    }

    public function index()
    {
        $drawings = SavedDrawing::where('user_id', Auth::id())->get();
        return view('account', compact('drawings'));
    }
    public function destroy($id)
    {
        $drawing = SavedDrawing::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        
        $drawing->delete();
        
        return redirect('/account');
    }
}