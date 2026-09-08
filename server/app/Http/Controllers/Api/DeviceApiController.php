<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceApiController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        $device = Device::where('device_uuid', $data['device_uuid'])->first();
        $enrollmentKey = (string) ($data['enrollment_key'] ?? '');
        unset($data['enrollment_key']);

        if (! $device) {
            abort_unless($this->validEnrollmentKey($enrollmentKey), 401, 'Valid enrollment key required.');
            $device = new Device(['device_uuid' => $data['device_uuid']]);
        } elseif ($device->api_token_hash && ! $this->validBearer($request, $device)
            && ! $this->validEnrollmentKey($enrollmentKey)) {
            abort(401, 'Device authentication failed.');
        }

        $plainToken = Str::random(64);
        $device->fill($data + ['last_seen_at' => now(), 'is_active' => true]);
        $device->api_token_hash = hash('sha256', $plainToken);
        $device->api_token_issued_at = now();
        $created = ! $device->exists;
        $device->save();

        return response()->json([
            'data' => $device,
            'api_token' => $plainToken,
            'message' => $created ? 'Device registered.' : 'Device updated.',
        ], $created ? 201 : 200);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $this->validated($request, false);
        $device = Device::where('device_uuid', $data['device_uuid'])->firstOrFail();
        $this->requireBearerWhenEnrolled($request, $device);
        unset($data['enrollment_key']);
        $device->update($data + ['last_seen_at' => now(), 'is_active' => true]);

        return response()->json(['data' => $device, 'message' => 'Heartbeat recorded.']);
    }

    private function validated(Request $request, bool $registration): array
    {
        $rules = [
            'device_uuid' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'department' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['required', 'string', 'max:50'],
        ];
        if ($registration) $rules['enrollment_key'] = ['nullable', 'string', 'max:255'];
        return $request->validate($rules);
    }

    private function validEnrollmentKey(string $provided): bool
    {
        $expected = (string) config('app.agent_enrollment_key');
        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    private function validBearer(Request $request, Device $device): bool
    {
        $token = (string) $request->bearerToken();
        return $token !== '' && $device->api_token_hash
            && hash_equals($device->api_token_hash, hash('sha256', $token));
    }

    private function requireBearerWhenEnrolled(Request $request, Device $device): void
    {
        // Transitional compatibility: pre-enrollment devices keep working until
        // an updated Agent registers once and receives its per-device token.
        if (! $device->api_token_hash) return;
        abort_unless($this->validBearer($request, $device), 401, 'Device authentication failed.');
    }
}
