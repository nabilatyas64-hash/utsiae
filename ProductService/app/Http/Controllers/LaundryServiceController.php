<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LaundryService;
use App\Http\Resources\ProductResource;

class LaundryServiceController extends Controller
{
    public function index()
    {
        $services = LaundryService::all();

        return response()->json([
            'status' => 'success',
            'data' => ProductResource::collection($services)
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'unit' => 'required|string|max:255',
            'estimated_duration' => 'required|string|max:255',
            'is_available' => 'boolean',
        ]);

        $service = LaundryService::create($validatedData);

        return response()->json([
            'status' => 'success',
            'message' => 'Service created successfully',
            'data' => new ProductResource($service)
        ], 201);
    }

    public function show($id)
    {
        $service = LaundryService::findOrFail($id);

        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new ProductResource($service)
        ]);
    }
}