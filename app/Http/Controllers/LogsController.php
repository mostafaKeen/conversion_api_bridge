<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\WebhookLog;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    /**
     * Show all companies with their logs (optional filter by company)
     */
    public function index(Request $request)
    {
        $companies = Company::orderBy('name')->get();
        $selectedCompanyId = $request->query('company_id');

        $logsQuery = WebhookLog::with('company')->latest();

        if ($selectedCompanyId) {
            $logsQuery->where('company_id', $selectedCompanyId);
        }

        $logs = $logsQuery->paginate(20)->withQueryString();

        return view('logs.index', compact('companies', 'logs', 'selectedCompanyId'));
    }

    /**
     * Show single log detail
     */
    public function show(WebhookLog $log)
    {
        return view('logs.show', compact('log'));
    }
}
