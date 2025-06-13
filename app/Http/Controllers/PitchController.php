<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Pitch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PitchController extends Controller
{
    use ApiResponser;

    public function index(Request $request)
    {
        try {
            $pitches = Pitch::where('establishment_id', auth()->id())
                ->withTrashed() // Incluye campos eliminados (soft deleted)
                ->get()
                ->map(function ($pitch) {
                    return [
                        'id' => $pitch->id,
                        'name' => $pitch->name,
                        'location' => $pitch->location,
                        'price' => $pitch->price,
                        'description' => $pitch->description,
                        'establishment' => [
                            'id' => $pitch->establishment->id,
                            'name' => $pitch->establishment->name,
                        ],
                        'deleted_at' => $pitch->deleted_at,
                    ];
                });

            return $this->successResponse(['pitches' => $pitches], 'Campos obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de campos');
        }
    }

    public function availablePitches(Request $request)
    {
        try {
            $pitches = Pitch::whereNull('deleted_at')->with('establishment')->get()->map(function ($pitch) {
                return [
                    'id' => $pitch->id,
                    'name' => $pitch->name,
                    'location' => $pitch->location,
                    'price' => $pitch->price,
                    'description' => $pitch->description,
                    'establishment' => [
                        'id' => $pitch->establishment->id,
                        'name' => $pitch->establishment->name,
                    ],
                ];
            });

            return $this->successResponse(['pitches' => $pitches], 'Campos disponibles obtenidos con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'obtención de campos disponibles');
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $pitch = Pitch::where('establishment_id', auth()->id())->findOrFail($id);
            return $this->successResponse(['pitch' => $pitch], 'Campo obtenido con éxito');
        } catch (\Exception $e) {
            Log::error('Error en obtención de campo', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);
            return $this->handleException($e, $request, 'obtención de campo');
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:1000',
            ]);

            $pitch = DB::transaction(function () use ($validated) {
                return Pitch::create([
                    'establishment_id' => auth()->id(),
                    'name' => $validated['name'],
                    'location' => $validated['location'],
                    'price' => $validated['price'],
                    'description' => $validated['description'],
                ]);
            });

            Log::info('Campo creado con éxito', ['pitch_id' => $pitch->id, 'user_id' => auth()->id()]);

            return $this->successResponse(['pitch' => $pitch], 'Campo creado con éxito', 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'creación de campo');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
                'description' => 'nullable|string',
            ]);

            $pitch = Pitch::where('establishment_id', auth()->id())->findOrFail($id);

            $pitch->update($validated);

            Log::info('Campo actualizado con éxito', ['pitch_id' => $pitch->id, 'user_id' => auth()->id()]);

            return $this->successResponse(['pitch' => $pitch], 'Campo actualizado con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'actualización de campo');
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $pitch = Pitch::where('establishment_id', auth()->id())->findOrFail($id);

            $pitch->delete();

            Log::info('Campo marcado como eliminado con éxito', ['pitch_id' => $pitch->id, 'user_id' => auth()->id()]);

            return $this->successResponse([], 'Campo eliminado con éxito');
        } catch (\Exception $e) {
            return $this->handleException($e, $request, 'eliminación de campo');
        }
    }
}
