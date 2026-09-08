<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::latest('last_seen_at')->paginate(20),
        ]);
    }
}
