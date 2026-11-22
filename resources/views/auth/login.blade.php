<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | CBED Data System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="{{ asset('js/password-visibility.js') }}" defer></script>
    <style>
        .login-page {
            --login-navy: #071f49;
            --login-gold: #ffc247;
            min-height: 100vh;
            min-height: 100svh;
            background: #f8fafc;
            color: var(--login-navy);
        }

        .login-decoration {
            position: absolute;
            inset: 0;
            z-index: -1;
            overflow: hidden;
            pointer-events: none;
        }

        .login-decoration::before,
        .login-decoration::after {
            position: absolute;
            width: 78vw;
            height: 30vw;
            border-radius: 50%;
            background: var(--login-navy);
            content: "";
        }

        .login-decoration::before {
            top: -23vw;
            right: -14vw;
            border-bottom: clamp(6px, 1vw, 16px) solid var(--login-gold);
            transform: rotate(15deg);
        }

        .login-decoration::after {
            right: -26vw;
            bottom: -25vw;
            border-top: clamp(6px, 1vw, 16px) solid var(--login-gold);
            transform: rotate(-10deg);
        }

        .login-layout {
            padding-block: 40px clamp(.75rem, 3svh, 2rem);
            align-content: start;
        }

        @media (min-width: 1024px) {
            .login-layout {
                align-content: stretch;
            }
        }

        .login-card {
            zoom: 0.8;
            background: rgb(255 255 255 / 98%);
            border: 2px solid var(--login-navy);
            box-shadow: 0 1rem 3rem rgb(7 31 73 / 17%);
        }

        .login-photo {
            min-height: clamp(8rem, calc(100svh - 24rem), 27rem);
            background: linear-gradient(#f8fafc, transparent 34%),
                url("{{ asset('images/login-carabao.jpg') }}") center 64% / cover no-repeat;
        }

        .login-program {
            background: var(--login-navy);
            box-shadow: .4rem .3rem var(--login-gold);
        }

        .login-focus-areas {
            background: var(--login-navy);
            border-top: .3rem solid var(--login-gold);
        }

        .login-form input {
            min-height: 3.125rem;
            width: 100%;
            padding-inline-start: 2.75rem;
            border-color: #cbd5e1;
            background: #f8fafc;
            font-size: 1rem;
            transition: border-color .15s, background-color .15s, box-shadow .15s;
        }

        .login-form input:hover {
            border-color: #94a3b8;
        }

        .login-form input:focus {
            outline: 2px solid transparent;
            border-color: #2563eb;
            background: #fff;
            box-shadow: 0 0 0 3px #2563eb22;
        }

        .login-form [data-password-toggle] {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: .5rem;
        }

        .login-form [data-password-toggle]:hover {
            background: #e2e8f0;
            color: #0b1f3a;
        }

        .login-form button:focus-visible {
            outline: 2px solid #2563eb;
            outline-offset: 2px;
        }

        @media (max-height: 780px) {
            .login-layout {
                row-gap: 1rem;
            }

            .login-focus-areas {
                padding-block: .625rem;
            }

            .login-card {
                padding-block: 1.75rem;
            }
        }

        @media (max-height: 600px) and (max-width: 1023px) {
            .login-program {
                display: none;
            }

            .login-card-heading {
                margin-bottom: 1rem;
            }

        }

        @media (max-height: 500px) {
            .login-photo {
                min-height: 5rem;
            }

            .login-illustration ul {
                display: none;
            }

            .login-card {
                padding-block: 1.25rem;
            }

            .login-card-heading {
                margin-bottom: .875rem;
            }

        }

        @media (max-height: 480px) and (max-width: 1023px) {
            .login-agencies img {
                width: 1.75rem;
                height: 1.75rem;
            }

        }
    </style>
</head>
<body class="login-page relative isolate flex flex-col">
    <div class="login-decoration" aria-hidden="true"></div>
    <header class="mx-auto w-full max-w-[100rem] px-4 pt-3 sm:px-8 lg:px-12 lg:pt-5">
        <div class="login-agencies flex items-center justify-center gap-3 text-xs font-bold leading-tight sm:text-sm lg:justify-start">
            <img src="{{ asset('images/da-pcc-logo.png') }}" alt="Department of Agriculture and Philippine Carabao Center" width="48" height="48" class="h-12 w-12 shrink-0 object-contain">
            <span>
                <span class="block">Philippine Carabao Center</span>
                <span class="block">National Institute for Animal Sciences</span>
            </span>
        </div>
    </header>
    <main class="login-layout mx-auto grid w-full max-w-[100rem] flex-1 items-center gap-5 px-4 sm:px-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)] lg:gap-10 lg:px-12">
        <section aria-labelledby="cbed-brand-title" class="min-w-0 self-start lg:flex lg:flex-col lg:self-stretch">
            <div class="relative z-10 mx-auto max-w-3xl text-center">
                <h2 id="cbed-brand-title" class="text-base font-bold italic leading-tight sm:text-lg xl:text-xl">Carabao-Based Enterprise Development</h2>
                <p class="login-program mx-auto mt-3 flex w-fit max-w-full items-center gap-2 rounded-full px-5 py-2 text-left text-xs font-bold italic leading-tight text-white sm:text-sm">
                    <i class="fa-solid fa-venus shrink-0 text-lg" aria-hidden="true"></i>
                    Female Carabao Entrustment and Milk Production Management
                </p>
            </div>
            <div class="login-illustration hidden overflow-hidden lg:flex lg:flex-1 lg:flex-col">
                <div class="login-photo flex-1" role="img" aria-label="A carabao and her calf on a Philippine farm"></div>
                    <ul class="login-focus-areas grid shrink-0 grid-cols-4 divide-x divide-white/25 rounded-b-2xl px-2 py-3 text-center text-[11px] font-semibold leading-tight text-white">
                        <li class="px-2"><i class="fa-solid fa-venus mb-1 block text-sm" aria-hidden="true"></i>Female Carabao Entrustment</li>
                        <li class="px-2"><i class="fa-solid fa-bottle-droplet mb-1 block text-sm" aria-hidden="true"></i>Milk Production</li>
                        <li class="px-2"><i class="fa-solid fa-chart-line mb-1 block text-sm" aria-hidden="true"></i>Enterprise Development</li>
                        <li class="px-2"><i class="fa-solid fa-people-group mb-1 block text-sm" aria-hidden="true"></i>Farmer &amp; Cooperative Support</li>
                </ul>
            </div>
        </section>

        <section aria-labelledby="sign-in-title" class="login-card mx-auto flex w-full max-w-xl items-center rounded-[2rem] px-6 py-8 sm:px-11 sm:py-12 lg:self-stretch xl:px-14 xl:py-16">
            <div class="w-full">
                <h1 id="sign-in-title" class="login-card-heading mb-6 text-center text-3xl font-bold tracking-tight">Sign in</h1>
                @if ($errors->any())
                    <p class="mb-5 rounded-lg border border-red-100 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</p>
                @endif

                <form method="POST" action="{{ route('login.post') }}" class="login-form space-y-5">
                    @csrf
                    <label class="block text-sm font-semibold">Email
                        <span class="relative mt-2 block">
                            <i class="fa-regular fa-envelope pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-500" aria-hidden="true"></i>
                            <input class="block rounded-lg border px-3 py-2.5 font-normal"
                                   name="email" type="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="username" inputmode="email" maxlength="255" required>
                        </span>
                    </label>
                    <label class="block text-sm font-semibold">Password
                        <span class="relative mt-2 block">
                            <i class="fa-solid fa-lock pointer-events-none absolute left-4 top-1/2 z-10 -translate-y-1/2 text-slate-500" aria-hidden="true"></i>
                            <x-password-input autocomplete="current-password" required />
                        </span>
                    </label>
                    <button type="submit" class="group flex min-h-[3.125rem] w-full items-center justify-center gap-2 rounded-lg bg-[#0b1f3a] px-4 py-3 font-semibold text-white shadow-sm transition-colors hover:bg-[#184069] active:bg-[#08172b]">
                        <span>Sign in</span>
                        <i class="fa-solid fa-right-to-bracket text-sky-100 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true"></i>
                    </button>
                </form>
                <p class="mt-6 text-center text-sm text-slate-600">Need help signing in? Contact your CBED administrator.</p>
            </div>
        </section>
    </main>
</body>
</html>
