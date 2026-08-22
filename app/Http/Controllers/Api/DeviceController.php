<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        dd('hi');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDeviceRequest $request)
    {
        // log
        Log::info('Device registration attempt', ['data' => $request->all()]);

        // we will check if the token exist and is valid (not used and not expired)
        $registration_token = app("App\Services\RegistrationTokenService")->validateToken($request->input('registration_token'));

        if (! $registration_token) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        $device = DB::transaction(function () use ($request, $registration_token) {

            $device = app('App\Services\DeviceService')->createDevice([
                'client_id' => $registration_token->client_id,
                ...$request->validated(),
            ]);

            // update the registration token as used
            $registration_token->update(['used_at' => now(), 'device_id' => $device->id]);

            // update financing plan
            app('App\Services\FinancingPlanService')->updateFinancingPlanByToken($registration_token->id, ['device_id' => $device->id]);

            return $device;
        });

        // now we can generate token sanctum unique for this device who will use to connect anytime on phone mobile
        $token = $device->createToken('device-token', ['device:*'])->plainTextToken;

        return (new DeviceResource($device))->additional(['token' => $token]);
    }

    public function userProfile(Request $request)
    {
        $device = $request->user();
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        return new DeviceResource($device);
    }

    /**
     * Display the specified resource.
     */
    public function show(Device $device)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Device $device)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeviceRequest $request, Device $device)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device)
    {
        //
    }

    public function refresh(Request $request)
    {
        $device = Device::whereHas('client', function ($query) use ($request) {
            $query->where('reference', $request->input('matricule'))->orderBy('created_at', 'desc');
        })->first();

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        //   $device->update(['last_activity_at' => now()]);

        // Révoquer l'ancien token et en créer un nouveau
        //  $request->user()->currentAccessToken()->delete();
        $token = $device->createToken('device-token', ['device:*'])->plainTextToken;

        // return response()->json([
        //     'device' => $device,
        //     'token' => $token,
        //     'token_type' => 'Bearer',
        // ]);

        return (new DeviceResource($device))->additional(['token' => $token]);
    }

    //  Add stockAdd method to handle the stock add form submission
    public function stockAdd(Phone $record, array $data)
    {
        dd($record, $data);
        // validate the data
        $validated = validator($data, [
            'quantity' => 'required|integer|min:1',
        ])->validate();

        // add the stock to the phone
        $record->stock += $validated['quantity'];
        $record->save();

        // redirect back to the phone list with a success message
        return redirect()->route('filament.resources.phones.index')->with('success', 'Stock ajouté avec succès.');

    }
}
