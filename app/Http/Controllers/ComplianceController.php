<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ComplianceController extends Controller
{
    public function index(Request $request)
    {
        // TODO: Implement compliance documents listing
        return $this->error('Not implemented', null, 501);
    }

    public function upload(Request $request)
    {
        // TODO: Implement document upload
        return $this->error('Not implemented', null, 501);
    }

    public function destroy($id)
    {
        // TODO: Implement document deletion
        return $this->error('Not implemented', null, 501);
    }
}

