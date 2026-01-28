<?php

// app/Http/Controllers/CompanyController.php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::latest()->paginate(10);
        return view('companies.index', compact('companies'));
    }

    public function create()
    {
        return view('companies.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'fb_pixel_id' => 'required|string',
            'fb_access_token' => 'required|string',
            'bitrix_webhook_url' => 'required|url',
            'bitrix_inbound_token' => 'required|string|unique:companies',
        ]);

        $data['outbound_token'] = Str::uuid();
        $data['is_active'] = true;

        Company::create($data);

        return redirect()->route('companies.index')
            ->with('success', 'Company created successfully');
    }

    public function edit(Company $company)
    {
        return view('companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'fb_pixel_id' => 'required|string',
            'fb_access_token' => 'required|string',
            'bitrix_webhook_url' => 'required|url',
            'bitrix_inbound_token' => 'required|string|unique:companies,bitrix_inbound_token,' . $company->id,
            'is_active' => 'required|boolean',
        ]);

        $company->update($data);

        return redirect()->route('companies.index')
            ->with('success', 'Company updated successfully');
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully');
    }
}
