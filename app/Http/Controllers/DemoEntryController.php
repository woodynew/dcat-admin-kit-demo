<?php

namespace App\Http\Controllers;

use Dcat\Admin\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DemoEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->isMethod('POST'), 405);
        $request->validate(['username' => ['sometimes', 'required', 'string', Rule::in(['demo'])]]);

        $model = config('admin.database.users_model');
        $user = $model::query()->where('username', 'demo')->first();
        abort_unless($user, 503, '演示尚未安装，请先运行 demo:install。');
        abort_unless((int) $user->getAuthIdentifier() > 1 && ! $user->isAdministrator() && $user->roles->count() === 1 && $user->isRole('demo'), 403);

        Admin::guard()->login($user, false);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect(admin_url('demo/overview'));
    }
}
