<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\AuthorizesCompany;

class PropertyOwnerController extends Controller
{
    use AuthorizesCompany;

    /**
     * Full list for the company, used to populate the Owner Name dropdown
     * on the Property Create/Edit forms (parent property + child units).
     */
    public function index(Company $company)
    {
        $this->authorizeCompany($company);

        $owners = PropertyOwner::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['data' => $owners]);
    }

    /**
     * Add a new Owner to the list ("+ Add New" popup).
     */
    public function store(Request $request, Company $company)
    {
        $this->authorizeCompany($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim($data['name']);

        $exists = PropertyOwner::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($exists) {
            return response()->json(['data' => $exists]);
        }

        $owner = PropertyOwner::create([
            'company_id' => $company->id,
            'name'       => $name,
        ]);

        return response()->json(['data' => $owner]);
    }

    /**
     * Rename an Owner. Also mass-updates every property and child unit
     * currently storing the OLD name (on both `properties.owner_name` and
     * `property_units.owner_name`) so already-saved records stay in sync
     * with the master list — there's no FK to this table, so without this
     * the rename would only affect future selections.
     */
    public function update(Request $request, Company $company, PropertyOwner $propertyOwner)
    {
        $this->authorizeCompany($company);
        abort_unless($propertyOwner->company_id === $company->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $newName = trim($data['name']);

        $duplicate = PropertyOwner::where('company_id', $company->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($newName)])
            ->where('id', '!=', $propertyOwner->id)
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'That name is already used.'], 422);
        }

        $oldName = $propertyOwner->name;

        $propertyOwner->update(['name' => $newName]);

        if ($oldName !== $newName) {
            Property::where('company_id', $company->id)
                ->where('owner_name', $oldName)
                ->update(['owner_name' => $newName]);

            PropertyUnit::where('company_id', $company->id)
                ->where('owner_name', $oldName)
                ->update(['owner_name' => $newName]);
        }

        return response()->json(['data' => $propertyOwner]);
    }

    /**
     * Remove an Owner from the managed list. Properties that already have
     * this name saved keep it as-is — deleting here only stops it being
     * suggested to other properties going forward.
     */
    public function destroy(Company $company, PropertyOwner $propertyOwner)
    {
        $this->authorizeCompany($company);
        abort_unless($propertyOwner->company_id === $company->id, 404);

        $propertyOwner->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
