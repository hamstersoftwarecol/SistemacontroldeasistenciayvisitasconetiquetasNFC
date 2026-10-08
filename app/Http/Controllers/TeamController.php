<?php

namespace App\Http\Controllers;

use App\Services\TeamStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(private readonly TeamStatusService $team) {}

    public function index(): View
    {
        return view('team.index', ['board' => $this->team->board()]);
    }

    public function status(): JsonResponse
    {
        return response()->json($this->team->board());
    }
}
