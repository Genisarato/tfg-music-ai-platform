<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Handles the public landing page.
 *
 * This controller serves the initial entry point of the application,
 * rendering the Landing page for unauthenticated users.
 */
class StartController extends Controller
{
    /**
     * Display the public landing page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('Landing');
    }
}