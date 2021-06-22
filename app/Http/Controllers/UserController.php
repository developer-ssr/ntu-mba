<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;

class UserController extends Controller
{
    protected $links = [
        1 => 'https://explicit.splitsecondsurveys.co.uk/test?code=515630a6-f287-41f8-bba5-3063f6d84a3e&fflogo=1',
        2 => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=XaDe2uJ5rO',
        3 => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=uxK0dKPvqG',
        4 => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=UskiAg0F2S',
        5 => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=nfiuDOm5L7',
    ];

    protected $screeners = [
        2 => 'https://explicit.splitsecondsurveys.co.uk/test?code=3a4094a1-4324-4fc4-96c6-4e85702558d9&fflogo=1',
        3 => 'https://explicit.splitsecondsurveys.co.uk/test?code=d4e0028a-1d70-45df-957e-a53bf2f9b219&fflogo=1',
        4 => 'https://explicit.splitsecondsurveys.co.uk/test?code=d3a0de6a-c480-4848-a0dc-c738207da8ba&fflogo=1',
        5 => 'https://explicit.splitsecondsurveys.co.uk/test?code=dc63c35a-b5af-40f8-bad7-1f4dd0914167&fflogo=1',
        6 => 'https://explicit.splitsecondsurveys.co.uk/test?code=20478397-717b-49f7-8cde-6e0316deafd4&fflogo=1'
    ];

    protected $urls = [
        'p2a' => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=XaDe2uJ5rO',//SaU71e
        'p1' => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=uxK0dKPvqG',//uUAjdl
        'p2b' => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=UskiAg0F2S',//FGHgxS1iyk
        'p4' => 'https://eaat2.splitsecondsurveys.co.uk/engine/#/?code=nfiuDOm5L7',//7V7kVm
        
    ];

    protected $so = "https://survey.thefemalelead.com/#/so";

    protected $dashboard = "https://survey.thefemalelead.com/#/";

    protected $source_server = "https://eaat2.splitsecondsurveys.co.uk/api/record/";

    public function entry()
    {
        $request = request()->all();
        $username = $this->userName();
        $id = $request['id'] ?? $this->uuid();
        User::create([
            'username' => $username,
            'info' => collect(['id' => $id, 'ip' => request()->ip()])->merge($request)->toArray(),
            'org' => $request['org'] ?? 'ntu'
        ]);
        session(['username' => $username]);
        return redirect()->route('route', ['link' => 1, 'id' => $id, 'username' => $username]);
    }

    private function userName()
    {
        do {
            $username = Str::random(15) . rand(0, 10000);
        } while (User::where('username', $username)->exists());
        return $username;
    }

    private function uuid()
    {
        return Uuid::uuid4();
    }

    public function go_to(Request $request)
    {
        if ($request->has('part')) {
            $user = User::where('username', ($request->username ?? null))->first();
            if ($user) {
                $info = $user->info;
                $part = collect($this->urls)->keys()->filter(function ($v) use ($request) {
                    return $request->part == $v;
                })->keys()->first();
                $info[collect($this->urls)->keys()[$part - 1]] = true;
                $info[$request->part] = false;
                $user->update([
                    'info' => $info
                ]);
            }
            return redirect($this->urls[$request->part] . '&' . http_build_query($request->except('part')));
        } else {
            return "Part variable missing!";
        }
    }

    public function so(Request $request)
    {
        $username = session('username');
        $user = User::whereUsername($username)->first();
        if ($user) {
            $user->update([
                'is_qualified' => false
            ]);
            session()->forget('username');
            return redirect($this->so);
        } else {
            return 'Invalid user. Session not set';
        }
    }

    public function route(Request $request)
    {
        if (!$request->has('link') || !$request->has('username')) {
            return 'link or username is missing!';
        }
        $link = $request->link;
        $user = User::where('username', $request->username)->first();
        if (!$user) {
            return 'invalid user';
        }
        $screener = $user->config['screener'] ?? [];
        $config = $user->config;
        $config['link' . $request->link] = [
            'start' => now()->toDateTimeString()
        ];
        $user->update([
            'config' => $config,
        ]);
        if ($link == '1') {
            return redirect($this->links[$link] . '&id=' . $request->id . '&' . http_build_query($request->only('username', 'link')));
        } else {
            // go to survey
            return redirect($this->links[$link] . '&id=' . $this->uuid() . '&' . http_build_query(collect($request->only('username', 'link'))->merge($user->config['screener'])->toArray()));
        }
    }

    public function completes(Request $request)
    {
        $data = $request->all();
        if (!isset($data['username'])) {
            abort(503);
        }
        if (!isset($data['link'])) {
            return 'Unknown link';
        }
        $user = User::whereUsername($data['username'])->first();
        if (!$user) {
            return 'Unknown user';
        }
        $config = $user->config;
        $config['link' . $data['link']] = [
            'id' => $data['id'],
            'ip' => $request->ip(),
            'end' => now()->toDateTimeString(),
            'done' => true,
            'start' => $config['link' . $data['link']]['start']
        ];
        if ($data['link'] == 1) {
            $urls = collect($request->except('id', 'link', 'username'))->map(function ($value) {
                return $value == '0' ? null : $value;
            })->toArray();
            $urls['gender'] = $urls['a1'];
            $urls['trans'] = $urls['a2'];
            $urls['age'] = $urls['a3'];
            $urls['country'] = $urls['a4'];
            $config['screener'] = $urls;
        }
        $link_code = [
            1 => 'p4',
            2 => '51b',
            3 => '52b',
            4 => '53b',
            5 => '54b',
            6 => '55b'
        ];
        $info = $user->info;
        $info[$link_code[$data['link']]] = true;
        $user->update([
            'config' => $config,
            'data' => $this->getData($data, $user->data),
            'info' => $info
        ]);
        $scores = $user->scores;
        switch ($data['link']) {
            case 1:
                $scores['link1'] = [
                    'phase2' => $this->phase2($user->username),
                    'phase4' => $this->phase4($user->username),
                    'phaseB' => $this->phaseB($user->username, 1)
                ];
                break;
        }
        $user->update([
            'scores' => $scores
        ]);
        if (Auth::check()) {
            Auth::logout();
            Auth::login($user);
        } else {
            Auth::login($user);
        }
        return redirect(($data['link'] == 1 ? $this->register : $this->dashboard) . "?username=" . $user->username);
    }

    private function getData($data, $user_data)
    {
        switch ($data['link']) {
            case 1:
                $http1 = Http::get($this->source_server . 'XaDe2uJ5rO/' . $data['id']);
                $http2 = Http::get($this->source_server . 'uxK0dKPvqG/' . $data['id']);
                $http3 = Http::get($this->source_server . 'UskiAg0F2S/' . $data['id']);
                $http4 = Http::get($this->source_server . 'nfiuDOm5L7/' . $data['id']);
                $user_data['link1'] = [
                    'XaDe2uJ5rO' => json_decode($http1->body(), true),
                    'uxK0dKPvqG' => json_decode($http2->body(), true),
                    'UskiAg0F2S' => json_decode($http3->body(), true),
                    'nfiuDOm5L7' => json_decode($http4->body(), true)
                ];
                break;
            default:
                $user_data['screener'] = collect($data)->except('id', 'username', 'link', 'scrn', 'rlink')->toArray();
                break;
        }
        return $user_data;
    }
}
