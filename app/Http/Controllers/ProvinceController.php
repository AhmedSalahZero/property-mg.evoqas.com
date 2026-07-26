<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\Province;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class ProvinceController extends Controller
{
    use AuthorizesCompany;

    /**
     * Full list for the company, used to populate the Province/District
     * dropdown on the Property Create/Edit forms.
     */
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $provinces = Province::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $provinces]);
    }

    /**
     * Add a new Province/District to the list ("+ Add New" popup).
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $name = trim($data['name']);

        $exists = Province::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($exists) {
            return response()->json(['data' => $exists]);
        }

        $province = Province::create([
            'company_id' => $company->id,
            'name'       => $name,
        ]);

        return response()->json(['data' => $province]);
    }

    /**
     * Rename a Province/District. Also mass-updates every property
     * currently storing the OLD name so already-saved records stay in
     * sync with the master list — there's no FK from `properties.province`
     * to this table, so without this the rename would only affect future
     * selections.
     */
    public function update(Request $request, Company $company, Province $province)
    {
        $this->authorizeCompany($company);
        abort_unless($province->company_id === $company->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        $newName = trim($data['name']);

        $duplicate = Province::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
            ->where('id', '!=', $province->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'That name is already used.'], 422);
        }

        $oldName = $province->name;

        $province->update(['name' => $newName]);

        if ($oldName !== $newName) {
            Property::where('company_id', $company->id)
                ->where('province', $oldName)
                ->update(['province' => $newName]);
        }

        return response()->json(['data' => $province]);
    }

    /**
     * Remove a Province/District from the managed list. Properties that
     * already have this name saved keep it as-is — deleting here only
     * stops it being suggested to other properties going forward.
     */
    public function destroy(Company $company, Province $province)
    {
        $this->authorizeCompany($company);
        abort_unless($province->company_id === $company->id, 404);

        $province->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
