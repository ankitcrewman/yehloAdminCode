<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Serviceman;
use Illuminate\Support\Facades\Hash;
use App\Traits\FileManagerTrait;

class ServicemanController extends Controller
{
    use FileManagerTrait;
    public function create()
    {
        return view('admin-views.serviceman.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'f_name' => 'required|string|max:255',
            'l_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:servicemen',
            'store_id' => 'required|exists:stores,id',
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:8',
            'identity_type' => 'required|string|max:255',
            'identity_number' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpg,png,jpeg,gif,bmp,tif,tiff|max:2048',
            'identity_image' => 'required|array',
            'identity_image.*' => 'image|mimes:jpg,png,jpeg,gif,bmp,tif,tiff|max:2048',
        ]);

        // Handle file upload for the image
        if ($request->hasFile('image')) {

            $imagePath = $this->upload('service-man/', 'png', $request->file('image'));
        }

        // Handle multiple identity images
        $identityImages = [];
        if ($request->hasFile('identity_image')) {
            foreach ($request->file('identity_image') as $file) {
                $identityImages[] = $file->store('service-man-identification', 'public');
            }
        }

        // Insert data into the servicemen table
        $serviceman = Serviceman::create([
            'first_name' => $request->f_name,
            'last_name' => $request->l_name,
            'email' => $request->email,
            'store_id' => $request->store_id,
            'phone_number' => $request->phone,
            'password' => Hash::make($request->password),
            'document_type' => $request->identity_type,
            'identity_number' => $request->identity_number,
            'serviceman_image' => $imagePath ?? null,
            'identification_image' => json_encode($identityImages),
            'module_type' => 'services',
            'status' => '1',
        ]);

        // Redirect back with a success message
        return redirect()->back()->with('success', 'Serviceman created successfully!');
    }
}
