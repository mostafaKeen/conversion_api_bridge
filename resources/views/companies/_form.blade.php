@csrf

<div class="mb-3">
    <label class="form-label">Company Name</label>
    <input type="text" name="name" class="form-control"
           value="{{ old('name', $company->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Facebook Pixel ID</label>
    <input type="text" name="fb_pixel_id" class="form-control"
           value="{{ old('fb_pixel_id', $company->fb_pixel_id ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Facebook Access Token</label>
    <textarea name="fb_access_token" class="form-control" required>{{ old('fb_access_token', $company->fb_access_token ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label class="form-label">Bitrix Webhook URL</label>
    <input type="url" name="bitrix_webhook_url" class="form-control"
           value="{{ old('bitrix_webhook_url', $company->bitrix_webhook_url ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Bitrix Inbound Token</label>
    <input type="text" name="bitrix_inbound_token" class="form-control"
           value="{{ old('bitrix_inbound_token', $company->bitrix_inbound_token ?? '') }}" required>
</div>

@if(isset($company))
<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="is_active" class="form-select">
        <option value="1" {{ $company->is_active ? 'selected' : '' }}>Active</option>
        <option value="0" {{ !$company->is_active ? 'selected' : '' }}>Inactive</option>
    </select>
</div>
@endif

<button class="btn btn-success">Save</button>
