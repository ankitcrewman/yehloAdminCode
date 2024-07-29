<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Serviceman;
use Illuminate\Support\Facades\Hash;

class ServicemanController extends Controller
{
    public function create()
    {
        return view('admin-views.serviceman.create');
    }
}
