<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Stat Series Data Repository</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } } };
    </script>

    <style>
        [x-cloak] { display: none !important; }
        .field-label { display: block; margin-bottom: .25rem; font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .field {
            width: 100%; border: 1px solid #e2e8f0; background: #f8fafc; border-radius: .75rem;
            padding: .55rem .75rem; font-size: .875rem; color: #0f172a; transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .field:hover:not(:disabled) { border-color: #cbd5e1; background: #fff; }
        .field:focus-visible { outline: none; border-color: #059669; background: #fff; box-shadow: 0 0 0 4px rgba(5, 150, 105, .15); }
        .field:disabled { cursor: not-allowed; opacity: .55; }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>

<body class="bg-slate-50 font-sans text-slate-900 antialiased" x-data="indexer()" x-cloak>

    {{-- Top bar. Upper right: login boxes for guests, account menu once signed in. --}}
    <header class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <a href="/" class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/philrice-logo.png') }}" alt="PhilRice logo" class="h-9 w-9 shrink-0 object-contain">
                <span class="leading-tight {{ auth()->check() ? 'hidden sm:block' : '' }}">
                    <span class="block text-sm font-bold tracking-tight sm:text-base">STAT SERIES DATA REPOSITORY</span>
                    <span class="hidden text-xs text-slate-500 sm:block">Prices · Productions · Imports · Exports</span>
                </span>
            </a>

            <div class="flex shrink-0 items-center gap-2">
                @guest
                    {{-- Small screens: a button opens the boxes as a panel in the upper right. --}}
                    <button type="button" x-on:click="loginOpen = !loginOpen" :aria-expanded="loginOpen" aria-controls="login-form"
                            class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 lg:hidden">
                        Log in
                    </button>

                    <form id="login-form" method="POST" action="{{ route('login.attempt') }}"
                          class="absolute right-3 top-full z-50 mt-2 w-[min(20rem,calc(100vw-1.5rem))] flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl lg:static lg:mt-0 lg:w-auto lg:flex-row lg:items-center lg:gap-2 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none"
                          :class="loginOpen ? 'flex' : 'hidden lg:flex'" aria-label="Log in">
                        @csrf
                        <label class="block">
                            <span class="field-label lg:sr-only">Username</span>
                            <input id="login-username" type="text" name="username" value="{{ old('username') }}" placeholder="Username" required
                                   autocomplete="username" autocapitalize="none" spellcheck="false" class="field !py-1.5 lg:!w-36">
                        </label>
                        <label class="block">
                            <span class="field-label lg:sr-only">Password</span>
                            <input type="password" name="password" placeholder="Password" required autocomplete="current-password" class="field !py-1.5 lg:!w-36">
                        </label>
                        <button type="submit"
                                class="rounded-xl bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                            Log in
                        </button>
                        @if ($errors->any())
                            <p role="alert" class="text-sm font-semibold text-red-600 lg:absolute lg:right-4 lg:top-full lg:mt-1 lg:w-max lg:rounded-lg lg:bg-red-600 lg:px-3 lg:py-1.5 lg:text-xs lg:text-white lg:shadow-lg">
                                {{ $errors->first() }}
                            </p>
                        @endif
                    </form>
                @endguest

                @auth
                    <span class="mr-1 hidden text-right leading-tight sm:block">
                        <span class="block max-w-[11rem] truncate text-sm font-semibold">{{ auth()->user()->name }}</span>
                        <span class="block text-xs text-slate-500">{{ auth()->user()->roleLabel() }}</span>
                    </span>

                    @can('administer')
                        <button type="button" x-on:click="openAccounts()" title="Accounts" aria-label="Accounts"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                            <span class="hidden sm:inline">Accounts</span>
                        </button>
                        <button type="button" x-on:click="openUpload()" title="Add a file" aria-label="Add a file"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 sm:px-4">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            <span class="hidden sm:inline">Add a file</span>
                        </button>
                    @endcan

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Log out" aria-label="Log out"
                                class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                            <span class="hidden sm:inline">Log out</span>
                        </button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    {{-- Filters, at the top --}}
    <section class="z-30 border-b border-slate-200 bg-white shadow-sm lg:sticky lg:top-16" aria-label="Filters">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Category">
                    <button type="button" x-on:click="setCategory('')"
                            class="rounded-full px-4 py-1.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            :class="filters.category === '' ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            :aria-pressed="filters.category === ''">
                        All files <span class="ml-1 opacity-60" x-text="summary.total_files"></span>
                    </button>
                    <template x-for="(category, key) in categories" :key="key">
                        <button type="button" x-on:click="setCategory(key)"
                                class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                :class="filters.category === key ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                :aria-pressed="filters.category === key">
                            <span class="h-2 w-2 rounded-full" :class="swatch(key)"></span>
                            <span x-text="category.label"></span>
                            <span class="opacity-60" x-text="counts[key] || 0"></span>
                        </button>
                    </template>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" x-show="filters.tag" x-on:click="filters.tag = ''; search(true)"
                            class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800 hover:bg-emerald-200"
                            title="Remove tag filter">
                        <span x-text="'#' + filters.tag"></span><span aria-hidden="true">&times;</span>
                    </button>
                    <button type="button" x-show="hasFilters()" x-on:click="clearFilters()"
                            class="text-sm font-semibold text-emerald-700 hover:text-emerald-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                        Clear filters
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-12">
                <label class="lg:col-span-2">
                    <span class="field-label">Item</span>
                    <select class="field" x-model="filters.item" x-on:change="search(true)" :disabled="!filters.category">
                        <option value="" x-text="filters.category ? 'Any item' : 'Choose a category'"></option>
                        <template x-for="item in itemsFor(filters.category)" :key="item"><option :value="item" x-text="item"></option></template>
                    </select>
                </label>

                <label class="lg:col-span-2">
                    <span class="field-label">Period</span>
                    <select class="field" x-model="filters.period_type" x-on:change="setPeriodType()">
                        <option value="">Any time</option>
                        <template x-for="(period, key) in periods" :key="key"><option :value="key" x-text="period.label"></option></template>
                    </select>
                </label>

                <label class="lg:col-span-2">
                    <span class="field-label" x-text="filters.period_type ? periodNoun(filters.period_type) : 'Value'"></span>
                    <select class="field" x-model="filters.period_value" x-on:change="search(true)" :disabled="!filters.period_type">
                        <option value="" x-text="filters.period_type ? 'Every ' + periodNoun(filters.period_type).toLowerCase() : 'Pick a period first'"></option>
                        <template x-for="value in valuesFor(filters.period_type)" :key="value.value"><option :value="value.value" x-text="value.label"></option></template>
                    </select>
                </label>

                <label class="lg:col-span-3">
                    <span class="field-label">Region</span>
                    <select class="field" x-model="filters.region" x-on:change="search(true)" :disabled="!regionApplies(filters.category)">
                        <option value="" x-text="regionApplies(filters.category) ? 'All regions' : 'Productions only'"></option>
                        <template x-for="region in regions" :key="region"><option :value="region" x-text="region"></option></template>
                    </select>
                </label>

                <label class="sm:col-span-2 lg:col-span-3">
                    <span class="field-label">Search</span>
                    <span class="relative block">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                        <input type="search" class="field pl-9" placeholder="Title, file name or tag" x-model="filters.q" x-on:input.debounce.300ms="search(true)">
                    </span>
                </label>
            </div>
        </div>
    </section>

    {{-- Results + chart --}}
    <main class="mx-auto max-w-7xl px-4 pb-24 pt-6 sm:px-6 lg:grid lg:grid-cols-[minmax(0,1fr)_22rem] lg:gap-8 lg:px-8">

        <div aria-live="polite">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-baseline gap-3">
                    <h1 class="text-lg font-bold tracking-tight" x-text="showDeleted ? 'Deleted files' : 'Files'"></h1>
                    <p class="text-sm text-slate-500" x-text="loading && !files.length ? 'Searching…' : total + (total === 1 ? ' file' : ' files')"></p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-show="auth.isAdministrator" x-on:click="toggleDeleted()"
                            class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-sm font-semibold shadow-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                            :class="showDeleted ? 'bg-slate-900 text-white hover:bg-slate-800' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                        <span x-text="showDeleted ? 'Back to files' : 'Deleted files' + (summary.trashed_files ? ' (' + summary.trashed_files + ')' : '')"></span>
                    </button>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <span class="font-semibold">Sort by</span>
                        <select class="field !w-auto !py-1.5" x-model="sort" x-on:change="search(true)" aria-label="Sort files">
                            <option value="date_desc">Date: newest first</option>
                            <option value="date_asc">Date: oldest first</option>
                            <option value="name_asc">Name: A to Z</option>
                            <option value="name_desc">Name: Z to A</option>
                        </select>
                    </label>
                </div>
            </div>

            {{-- Guests can look at files but not download them --}}
            <div x-show="!auth.canDownload && files.length" class="mb-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-900">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v5h1"/></svg>
                <p>You are browsing as a guest. You can view files, but you need to log in to download them.</p>
                <button type="button" x-on:click="goToLogin()" class="font-semibold underline hover:text-sky-700">Log in</button>
            </div>

            {{-- Selection bar (clients and administrators) --}}
            <div x-show="auth.canDownload && !showDeleted && files.length" class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm">
                <label class="inline-flex cursor-pointer items-center gap-2.5 font-semibold text-slate-700">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                           :checked="allShownSelected()" x-on:change="toggleAllShown($event.target.checked)">
                    Select all shown
                </label>
                <div class="flex items-center gap-3">
                    <span class="text-slate-500" x-show="selectedIds.length" x-text="selectedIds.length + (selectedIds.length === 1 ? ' file selected' : ' files selected')"></span>
                    <button type="button" x-show="selectedIds.length" x-on:click="selectedIds = []" class="font-semibold text-slate-600 hover:text-slate-900">Clear</button>
                    <button type="button" x-on:click="downloadSelected()" :disabled="!selectedIds.length"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                        Download selected (.zip)
                    </button>
                </div>
            </div>

            <form x-ref="selectedForm" method="POST" :action="urls.downloadSelected" class="hidden">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="sort" :value="sort">
                <template x-for="id in selectedIds" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
            </form>

            <ul class="space-y-3">
                <template x-for="file in files" :key="file.id">
                    <li class="group flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-emerald-300 hover:shadow-md sm:flex-row sm:items-center">
                        <input type="checkbox" class="h-4 w-4 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600"
                               x-show="auth.canDownload && !showDeleted" :value="file.id" x-model.number="selectedIds" :aria-label="'Select ' + file.title">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl text-[11px] font-extrabold uppercase ring-1 ring-inset"
                              :class="badge(file.extension)" x-text="file.extension"></span>

                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold" x-text="file.title"></h2>
                            <p class="mt-0.5 line-clamp-2 text-sm text-slate-500" x-show="file.description" x-text="file.description"></p>
                            <div class="mt-2 flex flex-wrap gap-1.5 text-xs font-medium">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="swatch(file.category)"></span>
                                    <span x-text="file.category_label + ' · ' + file.item"></span>
                                </span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700" x-text="file.period"></span>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700" x-show="file.region" x-text="file.region"></span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1" x-show="file.tags.length">
                                <template x-for="tag in file.tags" :key="tag">
                                    <button type="button" x-on:click="filterTag(tag)" title="Show files with this tag"
                                            class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 hover:bg-emerald-100">
                                        <span x-text="'#' + tag"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-2">
                            <span class="text-xs text-slate-500" x-text="file.size + ' · ' + (showDeleted ? 'Deleted ' + file.deleted : file.added)"></span>
                            <div class="flex items-center gap-1.5">
                                <a :href="file.view_url" x-show="!showDeleted" target="_blank" rel="noopener" title="Open in a new tab"
                                   class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg>
                                    Open
                                </a>
                                <a :href="file.download_url" x-show="auth.canDownload && !showDeleted"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                                    Download
                                </a>
                                <button type="button" x-show="auth.isAdministrator && !showDeleted" x-on:click="openEdit(file)" title="Edit file information" aria-label="Edit file information"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                </button>
                                <button type="button" x-show="showDeleted" x-on:click="restore(file)"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8M3 3v5h5"/></svg>
                                    Restore
                                </button>
                                <button type="button" x-show="auth.isAdministrator && !showDeleted" x-on:click="remove(file)" title="Delete" aria-label="Delete file"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                                </button>
                            </div>
                        </div>
                    </li>
                </template>

                {{-- Loading skeleton --}}
                <template x-if="loading && !files.length">
                    <li class="space-y-3" aria-hidden="true">
                        <div class="h-24 animate-pulse rounded-2xl bg-slate-200/70"></div>
                        <div class="h-24 animate-pulse rounded-2xl bg-slate-200/70"></div>
                        <div class="h-24 animate-pulse rounded-2xl bg-slate-200/70"></div>
                    </li>
                </template>

                {{-- Empty state --}}
                <li x-show="!loading && !files.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5"/></svg>
                    </span>
                    <p class="mt-4 text-lg font-bold" x-text="showDeleted ? 'No deleted files' : 'No files match these filters'"></p>
                    <p class="mt-1 text-sm text-slate-500"
                       x-text="showDeleted ? 'Files that an administrator deletes show up here and can be restored.' : (auth.isAdministrator ? 'Loosen a filter above, or add the first file for this combination.' : 'Loosen a filter above to see more files.')"></p>
                    <button type="button" x-show="auth.isAdministrator && !showDeleted" x-on:click="openUpload()" class="mt-5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Add a file</button>
                </li>
            </ul>

            <div class="mt-6 text-center" x-show="page < lastPage">
                <button type="button" x-on:click="search(false)" :disabled="loading"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2 text-sm font-semibold shadow-sm transition hover:border-emerald-400 hover:text-emerald-700 disabled:opacity-50">
                    Show more files
                </button>
            </div>
        </div>

        {{-- Right side: files per category --}}
        <aside class="mt-8 lg:sticky lg:top-72 lg:mt-0 lg:self-start" aria-labelledby="stats-title">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 id="stats-title" class="text-base font-bold tracking-tight">Files by category</h2>
                        <p class="text-sm text-slate-500" x-text="summary.total_files + (summary.total_files === 1 ? ' file' : ' files') + ' · ' + summary.total_size"></p>
                    </div>
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V9m5 10V5m5 14v-7m5 7V8"/></svg>
                    </span>
                </div>

                {{-- Chart --}}
                <ul class="mt-5 space-y-3" aria-label="Chart of files per category">
                    <template x-for="stat in summary.stats" :key="stat.key">
                        <li>
                            <button type="button" class="block w-full rounded-lg text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                                    x-on:click="filters.category === stat.key ? setCategory('') : setCategory(stat.key)"
                                    :aria-label="'Show ' + stat.label + ' files: ' + stat.files">
                                <span class="flex justify-between text-sm font-semibold">
                                    <span x-text="stat.label"></span>
                                    <span class="tabular-nums" x-text="stat.files"></span>
                                </span>
                                <span class="mt-1.5 block h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <span class="block h-2.5 rounded-full transition-[width] duration-500" :class="swatch(stat.key)" :style="'width:' + barWidth(stat) + '%'"></span>
                                </span>
                            </button>
                        </li>
                    </template>
                </ul>

                {{-- Table --}}
                <table class="mt-6 w-full text-left text-sm">
                    <caption class="sr-only">Number of files and total size per category</caption>
                    <thead>
                        <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                            <th scope="col" class="py-2 font-semibold">Category</th>
                            <th scope="col" class="py-2 text-right font-semibold">Files</th>
                            <th scope="col" class="py-2 text-right font-semibold">Size</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="stat in summary.stats" :key="stat.key">
                            <tr :class="filters.category === stat.key ? 'bg-emerald-50/70' : ''">
                                <th scope="row" class="py-2 pl-1 font-semibold">
                                    <span class="mr-2 inline-block h-2 w-2 rounded-full" :class="swatch(stat.key)"></span><span x-text="stat.label"></span>
                                </th>
                                <td class="py-2 text-right tabular-nums" x-text="stat.files"></td>
                                <td class="py-2 pr-1 text-right tabular-nums text-slate-600" x-text="stat.size"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-slate-200 font-bold">
                            <th scope="row" class="py-2 pl-1">Total</th>
                            <td class="py-2 text-right tabular-nums" x-text="summary.total_files"></td>
                            <td class="py-2 pr-1 text-right tabular-nums" x-text="summary.total_size"></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mt-6" x-show="summary.tags.length">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Popular tags</h3>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <template x-for="item in summary.tags" :key="item.tag">
                            <button type="button" x-on:click="filterTag(item.tag)"
                                    class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
                                    :class="filters.tag === item.tag ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'">
                                <span x-text="'#' + item.tag"></span> <span class="opacity-60" x-text="item.count"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </aside>
    </main>

    {{-- Upload dialog --}}
    <div x-show="uploading" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4 backdrop-blur-sm"
         x-on:keydown.escape.window="uploading = false" role="dialog" aria-modal="true" aria-labelledby="upload-title">
        <form class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl" x-on:submit.prevent="save()" x-on:click.outside="uploading = false" novalidate>
            <div class="mb-5 flex items-start justify-between">
                <div>
                    <h2 id="upload-title" class="text-xl font-bold tracking-tight" x-text="editingId ? 'Edit file information' : 'Add a file to the index'"></h2>
                    <p class="text-sm text-slate-500" x-text="editingId ? 'Change the name, tags and index details. The stored file itself stays the same.' : 'Tag it so it can be found by category, item, period and region.'"></p>
                </div>
                <button type="button" x-on:click="uploading = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="space-y-4">
                {{-- Drop zone (only when adding a file) --}}
                <div x-show="!editingId">
                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed px-4 py-6 text-center transition"
                           :class="[dragging ? 'border-emerald-500 bg-emerald-50' : 'border-slate-300 bg-slate-50 hover:border-emerald-400', errors.file ? 'border-red-400 bg-red-50' : '']"
                           x-on:dragover.prevent="dragging = true" x-on:dragleave.prevent="dragging = false" x-on:drop.prevent="drop($event)">
                        <svg class="h-8 w-8 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                        <span class="text-sm font-semibold" x-text="chosen ? chosen.name : 'Drop a file here, or click to browse'"></span>
                        <span class="text-xs text-slate-500" x-text="chosen ? sizeText(chosen.size) : 'PDF, CSV, XLS, XLSX, ZIP and other documents · up to ' + maxMb + ' MB'"></span>
                        <input type="file" class="sr-only" x-ref="file" x-on:change="pick()" :accept="extensions.map(extension => '.' + extension).join(',')">
                    </label>
                    <p class="mt-1.5 text-sm font-medium text-red-600" x-show="errors.file" x-text="errors.file"></p>
                    <p class="mt-1.5 text-xs text-amber-700" x-show="maxMb < appMaxMb">
                        Your PHP settings limit uploads to <span x-text="maxMb"></span> MB (the app allows <span x-text="appMaxMb"></span> MB).
                        Raise <code>upload_max_filesize</code> and <code>post_max_size</code> in php.ini, then restart the server.
                    </p>
                </div>

                <label class="block">
                    <span class="field-label">Title</span>
                    <input class="field" x-model="draft.title" placeholder="e.g. Palay farmgate prices, Week 12" required>
                    <span class="mt-1 block text-sm text-red-600" x-text="errors.title"></span>
                </label>

                <label class="block" x-show="editingId">
                    <span class="field-label">File name (used when the file is downloaded)</span>
                    <span class="flex items-center gap-2">
                        <input class="field" x-model="draft.file_name" maxlength="150">
                        <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-2 text-sm font-semibold text-slate-600" x-text="'.' + editingExtension"></span>
                    </span>
                    <span class="mt-1 block text-sm text-red-600" x-text="errors.file_name"></span>
                </label>

                <label class="block">
                    <span class="field-label">Description (optional)</span>
                    <textarea class="field" rows="2" x-model="draft.description"></textarea>
                </label>

                <div>
                    <span class="field-label">Tags (keywords)</span>
                    <div class="field flex flex-wrap items-center gap-1.5 !py-1.5 focus-within:border-emerald-600 focus-within:bg-white focus-within:ring-4 focus-within:ring-emerald-600/15" x-on:click="$refs.tagInput.focus()">
                        <template x-for="(tag, index) in draft.tags" :key="tag">
                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">
                                <span x-text="'#' + tag"></span>
                                <button type="button" x-on:click.stop="removeTag(index)" class="text-emerald-700 hover:text-red-600" :aria-label="'Remove tag ' + tag">&times;</button>
                            </span>
                        </template>
                        <input x-ref="tagInput" list="tag-suggestions" x-model="tagDraft" maxlength="30"
                               class="min-w-[9rem] flex-1 border-0 bg-transparent p-0 text-sm outline-none"
                               :placeholder="draft.tags.length ? '' : 'Type a tag, press Enter'"
                               x-on:keydown.enter.prevent="addTag()" x-on:keydown.comma.prevent="addTag()"
                               x-on:keydown.backspace="if (!tagDraft) draft.tags.pop()" x-on:blur="addTag()">
                    </div>
                    <datalist id="tag-suggestions">
                        <template x-for="item in summary.tags" :key="item.tag"><option :value="item.tag"></option></template>
                    </datalist>
                    <p class="mt-1 text-xs text-slate-500">Up to 10. Tags are searchable from the Search box or by clicking them.</p>
                    <p class="mt-1 text-sm text-red-600" x-show="errors.tags" x-text="errors.tags"></p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="field-label">Category</span>
                        <select class="field" x-model="draft.category" x-on:change="draft.item = ''; draft.region = ''">
                            <option value="">Choose…</option>
                            <template x-for="(category, key) in categories" :key="key"><option :value="key" x-text="category.label"></option></template>
                        </select>
                        <span class="mt-1 block text-sm text-red-600" x-text="errors.category"></span>
                    </label>
                    <label class="block">
                        <span class="field-label">Item</span>
                        <select class="field" x-model="draft.item" :disabled="!draft.category">
                            <option value="">Choose…</option>
                            <template x-for="item in itemsFor(draft.category)" :key="item"><option :value="item" x-text="item"></option></template>
                        </select>
                        <span class="mt-1 block text-sm text-red-600" x-text="errors.item"></span>
                    </label>
                    <label class="block">
                        <span class="field-label">Period</span>
                        <select class="field" x-model="draft.period_type" x-on:change="draft.period_value = ''">
                            <option value="">Choose…</option>
                            <template x-for="(period, key) in periods" :key="key"><option :value="key" x-text="period.label"></option></template>
                        </select>
                        <span class="mt-1 block text-sm text-red-600" x-text="errors.period_type"></span>
                    </label>
                    <label class="block">
                        <span class="field-label" x-text="draft.period_type ? periodNoun(draft.period_type) : 'Value'"></span>
                        <select class="field" x-model="draft.period_value" :disabled="!draft.period_type">
                            <option value="">Choose…</option>
                            <template x-for="value in valuesFor(draft.period_type)" :key="value.value"><option :value="value.value" x-text="value.label"></option></template>
                        </select>
                        <span class="mt-1 block text-sm text-red-600" x-text="errors.period_value"></span>
                    </label>
                </div>

                <label class="block" x-show="regionApplies(draft.category)">
                    <span class="field-label">Region</span>
                    <select class="field" x-model="draft.region">
                        <option value="">Choose…</option>
                        <template x-for="region in regions" :key="region"><option :value="region" x-text="region"></option></template>
                    </select>
                    <span class="mt-1 block text-sm text-red-600" x-text="errors.region"></span>
                </label>

                <p class="rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-show="errors.general" x-text="errors.general"></p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" x-on:click="uploading = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
                <button type="submit" :disabled="saving" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50">
                    <span x-text="saving ? (editingId ? 'Saving…' : 'Uploading…') : (editingId ? 'Save changes' : 'Add file')"></span>
                </button>
            </div>
        </form>
    </div>

    {{-- Accounts dialog (administrators) --}}
    @can('administer')
    <div x-show="accountsOpen" x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4 backdrop-blur-sm"
         x-on:keydown.escape.window="accountsOpen = false" role="dialog" aria-modal="true" aria-labelledby="accounts-title">
        <div class="flex max-h-[92vh] w-full max-w-2xl flex-col rounded-3xl bg-white shadow-2xl" x-on:click.outside="accountsOpen = false">
            <div class="flex items-start justify-between p-6 pb-3">
                <div>
                    <h2 id="accounts-title" class="text-xl font-bold tracking-tight">Accounts</h2>
                    <p class="text-sm text-slate-500">Administrators manage files and accounts. Clients can download files.</p>
                </div>
                <button type="button" x-on:click="accountsOpen = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 px-6">
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-semibold" role="tablist">
                    <button type="button" role="tab" :aria-selected="accountsTab === 'active'" x-on:click="setAccountsTab('active')"
                            class="rounded-lg px-3 py-1.5 transition" :class="accountsTab === 'active' ? 'bg-white shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                        Active <span class="opacity-60" x-text="accountCounts.active"></span>
                    </button>
                    <button type="button" role="tab" :aria-selected="accountsTab === 'deleted'" x-on:click="setAccountsTab('deleted')"
                            class="rounded-lg px-3 py-1.5 transition" :class="accountsTab === 'deleted' ? 'bg-white shadow-sm' : 'text-slate-600 hover:text-slate-900'">
                        Deleted <span class="opacity-60" x-text="accountCounts.deleted"></span>
                    </button>
                </div>
                <button type="button" x-show="accountsTab === 'active'" x-on:click="newAccount()"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    New account
                </button>
            </div>

            <form x-show="accountForm.open" x-on:submit.prevent="saveAccount()" class="mx-6 mt-4 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4" novalidate>
                <h3 class="text-sm font-bold" x-text="accountForm.id === null ? 'New account' : 'Edit account'"></h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="field-label">Full name</span>
                        <input class="field" x-model="accountForm.name" autocomplete="off" required>
                        <span class="mt-1 block text-sm text-red-600" x-text="accountErrors.name"></span>
                    </label>
                    <label class="block">
                        <span class="field-label">Username</span>
                        <input class="field" x-model="accountForm.username" autocomplete="off" autocapitalize="none" spellcheck="false" required>
                        <span class="mt-1 block text-sm text-red-600" x-text="accountErrors.username"></span>
                    </label>
                    <label class="block">
                        <span class="field-label" x-text="accountForm.id === null ? 'Password' : 'New password (optional)'"></span>
                        <input type="password" class="field" x-model="accountForm.password" autocomplete="new-password"
                               :placeholder="accountForm.id === null ? 'At least 8 characters' : 'Leave blank to keep the current one'">
                        <span class="mt-1 block text-sm text-red-600" x-text="accountErrors.password"></span>
                    </label>
                    <label class="block">
                        <span class="field-label">Account type</span>
                        <select class="field" x-model="accountForm.role">
                            <option value="client">Client (can download files)</option>
                            <option value="administrator">Administrator (full access)</option>
                        </select>
                        <span class="mt-1 block text-sm text-red-600" x-text="accountErrors.role"></span>
                    </label>
                </div>
                <p class="mt-1 text-xs text-slate-500">Usernames use lower-case letters, numbers, dots, dashes or underscores (3 to 30 characters).</p>
                <p class="mt-2 rounded-xl bg-red-50 px-3 py-2 text-sm font-medium text-red-700" x-show="accountErrors.general" x-text="accountErrors.general"></p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" x-on:click="accountForm = blankAccountForm()" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-white">Cancel</button>
                    <button type="submit" :disabled="savingAccount" class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                            x-text="savingAccount ? 'Saving…' : (accountForm.id === null ? 'Create account' : 'Save changes')"></button>
                </div>
            </form>

            <ul class="mt-4 min-h-[8rem] flex-1 space-y-2 overflow-y-auto px-6 pb-6">
                <template x-for="account in accounts" :key="account.id">
                    <li class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-100 text-sm font-bold uppercase text-slate-600" x-text="account.name.charAt(0)"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold" x-text="account.name + (account.is_self ? ' (you)' : '')"></p>
                            <p class="truncate text-xs text-slate-500" x-text="'@' + account.username + (account.deleted ? ' · deleted ' + account.deleted : '')"></p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="account.role === 'administrator' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700'" x-text="account.role_label"></span>
                        <div class="flex items-center gap-1">
                            <button type="button" x-show="accountsTab === 'active'" x-on:click="editAccount(account)" title="Edit account" aria-label="Edit account"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-emerald-50 hover:text-emerald-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </button>
                            <button type="button" x-show="accountsTab === 'active' && !account.is_self" x-on:click="deleteAccount(account)" title="Delete account" aria-label="Delete account"
                                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3"/></svg>
                            </button>
                            <button type="button" x-show="accountsTab === 'deleted'" x-on:click="restoreAccount(account)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 109-9 9.75 9.75 0 00-6.74 2.74L3 8M3 3v5h5"/></svg>
                                Restore
                            </button>
                        </div>
                    </li>
                </template>
                <li x-show="!accountsLoading && !accounts.length" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
                    x-text="accountsTab === 'deleted' ? 'No deleted accounts.' : 'No accounts yet.'"></li>
            </ul>
        </div>
    </div>
    @endcan

    {{-- Toast --}}
    <div x-show="toast" x-transition class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg" x-text="toast" role="status"></div>

    <script>
        window.agriIndex = @js($boot);
        window.agriIndex.loginFailed = @js($errors->any());

        function indexer() {
            const boot = window.agriIndex;
            const blankDraft = () => ({ file_name: '', title: '', description: '', category: '', item: '', period_type: '', period_value: '', region: '', tags: [] });
            const blankFilters = () => ({ category: '', item: '', period_type: '', period_value: '', region: '', tag: '', q: '' });
            const swatches = { prices: 'bg-amber-500', productions: 'bg-emerald-600', imports: 'bg-sky-500', exports: 'bg-violet-500' };

            return {
                categories: boot.categories,
                periods: boot.periods,
                regions: boot.regions,
                extensions: boot.extensions,
                maxMb: boot.maxMb,
                appMaxMb: boot.appMaxMb,
                counts: boot.counts,
                summary: boot.summary,
                urls: boot.urls,
                auth: boot.auth,
                loginOpen: !!boot.loginFailed,
                showDeleted: false,
                editingId: null, editingExtension: '',
                accountsOpen: false, accountsTab: 'active', accounts: [], accountCounts: { active: 0, deleted: 0 },
                accountsLoading: false, savingAccount: false, accountErrors: {},
                accountForm: { open: false, id: null, name: '', username: '', password: '', role: 'client' },
                sort: 'date_desc',
                selectedIds: [],
                bulkLimit: boot.bulkLimit,

                filters: blankFilters(),
                files: [], total: 0, page: 0, lastPage: 1, loading: false,

                uploading: false, saving: false, dragging: false, chosen: null, tagDraft: '',
                draft: blankDraft(), errors: {}, toast: '',

                init() {
                    this.search(true);
                    if (boot.flash) {
                        this.notify(boot.flash, 6000);
                        this.$nextTick(() => this.goToLogin());
                    }
                },

                goToLogin() {
                    this.loginOpen = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    this.$nextTick(() => { const box = document.getElementById('login-username'); if (box) box.focus(); });
                },

                /** One place for every JSON call: adds the CSRF token and handles an expired login. */
                async api(method, url, body) {
                    const options = { method, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content } };
                    if (body !== undefined) {
                        options.headers['Content-Type'] = 'application/json';
                        options.body = JSON.stringify(body);
                    }
                    const response = await fetch(url, options);
                    const result = await response.json().catch(() => ({}));
                    if (response.status === 401 || response.status === 419) {
                        this.notify('Your session has ended. Reloading…');
                        setTimeout(() => window.location.reload(), 1200);
                    }
                    return { ok: response.ok, status: response.status, result };
                },
                fileUrl(id) { return this.urls.files.replace(/\/$/, '') + '/' + id; },

                itemsFor(key) { return this.categories[key] ? this.categories[key].items : []; },
                valuesFor(type) { return this.periods[type] ? this.periods[type].values : []; },
                regionApplies(key) { return !!(this.categories[key] && this.categories[key].has_region); },
                periodNoun(type) { return { weekly: 'Week', monthly: 'Month', semester: 'Semester', yearly: 'Year' }[type] || 'Period'; },
                hasFilters() { return Object.values(this.filters).some(value => value !== ''); },

                swatch(key) { return swatches[key] || 'bg-slate-400'; },
                barWidth(stat) {
                    const largest = Math.max(...this.summary.stats.map(item => item.files), 0);
                    return largest ? Math.max(4, Math.round((stat.files / largest) * 100)) : 0;
                },
                sizeText(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB'; },

                badge(extension) {
                    if (extension === 'pdf') return 'bg-rose-50 text-rose-700 ring-rose-200';
                    if (['csv', 'xls', 'xlsx', 'ods'].includes(extension)) return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
                    if (['doc', 'docx', 'odt', 'rtf', 'txt'].includes(extension)) return 'bg-sky-50 text-sky-700 ring-sky-200';
                    if (['zip', 'json'].includes(extension)) return 'bg-amber-50 text-amber-700 ring-amber-200';
                    return 'bg-slate-100 text-slate-700 ring-slate-200';
                },

                setCategory(key) {
                    this.filters.category = key;
                    this.filters.item = '';
                    this.filters.region = '';
                    this.search(true);
                },
                setPeriodType() { this.filters.period_value = ''; this.search(true); },
                clearFilters() {
                    this.filters = blankFilters();
                    this.search(true);
                },

                filterParams() {
                    const params = new URLSearchParams();
                    Object.entries(this.filters).forEach(([name, value]) => { if (value) params.set(name, value); });
                    return params;
                },
                allShownSelected() { return this.files.length > 0 && this.files.every(file => this.selectedIds.includes(file.id)); },
                toggleAllShown(checked) {
                    const shownIds = this.files.map(file => file.id);
                    this.selectedIds = checked
                        ? [...new Set([...this.selectedIds, ...shownIds])]
                        : this.selectedIds.filter(id => !shownIds.includes(id));
                },
                downloadSelected() {
                    if (!this.selectedIds.length) return;
                    if (this.selectedIds.length > this.bulkLimit) {
                        this.notify('You can download up to ' + this.bulkLimit + ' files at once.');
                        return;
                    }
                    this.$nextTick(() => this.$refs.selectedForm.submit());
                },

                filterTag(tag) {
                    this.filters.tag = tag;
                    this.search(true);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                addTag() {
                    const tag = this.tagDraft.trim().replace(/^#/, '').toLowerCase().replace(/\s+/g, ' ').slice(0, 30);
                    this.tagDraft = '';
                    if (!tag || this.draft.tags.includes(tag) || this.draft.tags.length >= 10) return;
                    this.draft.tags.push(tag);
                },
                removeTag(index) { this.draft.tags.splice(index, 1); },

                async search(reset) {
                    if (reset) { this.page = 0; }
                    this.loading = true;
                    const params = this.filterParams();
                    params.set('page', this.page + 1);
                    params.set('sort', this.sort);
                    if (this.showDeleted) params.set('trashed', '1');

                    try {
                        const response = await fetch(this.urls.files + '?' + params, { headers: { Accept: 'application/json' } });
                        if (!response.ok) {
                            this.notify('Could not load files (HTTP ' + response.status + ' from ' + this.urls.files + '). Open the app from the address where it is served and run: php artisan optimize:clear', 9000);
                            return;
                        }
                        const result = await response.json();
                        this.files = reset ? result.data : this.files.concat(result.data);
                        this.page = result.current_page;
                        this.lastPage = result.last_page;
                        this.total = result.total;
                    } catch (error) {
                        this.notify('Could not load files. Check your connection and try again.');
                    } finally {
                        this.loading = false;
                    }
                },

                async loadSummary() {
                    try {
                        const response = await fetch(this.urls.stats, { headers: { Accept: 'application/json' } });
                        if (!response.ok) return;
                        this.summary = await response.json();
                        this.counts = Object.fromEntries(this.summary.stats.map(stat => [stat.key, stat.files]));
                    } catch (error) { /* keep the last known totals */ }
                },

                openUpload() {
                    this.editingId = null;
                    this.errors = {};
                    this.chosen = null;
                    if (this.$refs.file) this.$refs.file.value = '';
                    this.draft = Object.assign(blankDraft(), {
                        category: this.filters.category, item: this.filters.item,
                        period_type: this.filters.period_type, period_value: this.filters.period_value,
                        region: this.filters.region,
                    });
                    this.uploading = true;
                },

                pick() {
                    const file = this.$refs.file.files[0] || null;
                    this.chosen = file;
                    this.errors.file = '';

                    if (!file) return;

                    const extension = file.name.split('.').pop().toLowerCase();
                    if (!this.extensions.includes(extension)) {
                        this.errors.file = 'Files of type ".' + extension + '" are not allowed.';
                    } else if (file.size > this.maxMb * 1048576) {
                        this.errors.file = 'This file is ' + this.sizeText(file.size) + ' but the server accepts up to ' + this.maxMb + ' MB.';
                    }
                    if (!this.draft.title) this.draft.title = file.name.replace(/\.[^.]+$/, '');
                },
                drop(event) {
                    this.dragging = false;
                    if (!event.dataTransfer.files.length) return;
                    this.$refs.file.files = event.dataTransfer.files;
                    this.pick();
                },

                async save() {
                    this.errors = {};
                    if (this.editingId) { return this.saveEdit(); }
                    this.pick();
                    if (!this.chosen) this.errors.file = 'Choose a file to upload.';
                    if (this.errors.file) return;

                    this.addTag();
                    this.saving = true;
                    const body = new FormData();
                    Object.entries(this.draft).forEach(([name, value]) => {
                        if (name !== 'tags' && name !== 'file_name') body.append(name, value);
                    });
                    this.draft.tags.forEach(tag => body.append('tags[]', tag));
                    body.append('file', this.chosen);

                    try {
                        const response = await fetch(this.urls.store, {
                            method: 'POST',
                            body,
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                        });

                        if (response.status === 422) {
                            const result = await response.json();
                            Object.entries(result.errors).forEach(([name, messages]) => { this.errors[name] = messages[0]; });
                            return;
                        }
                        if (response.status === 413) {
                            this.errors.file = 'The server refused this file because it is too large (limit ' + this.maxMb + ' MB). Raise upload_max_filesize and post_max_size in php.ini.';
                            return;
                        }
                        if (response.status === 404) {
                            this.errors.general = 'Upload failed (404): ' + this.urls.store + ' was not found. Open the app from the address where it is served, then run: php artisan optimize:clear';
                            return;
                        }
                        if (response.status === 419) {
                            this.errors.general = 'Your session expired. Reload the page and try again.';
                            return;
                        }
                        if (!response.ok) {
                            const result = await response.json().catch(() => ({}));
                            this.errors.general = 'Upload failed (' + response.status + '). ' + (result.message || 'See storage/logs/laravel.log for details.');
                            return;
                        }

                        this.uploading = false;
                        this.chosen = null;
                        this.$refs.file.value = '';
                        this.notify('File added to the index.');
                        this.search(true);
                        this.loadSummary();
                    } catch (error) {
                        this.errors.general = 'Could not reach the server. Check that it is running and try again.';
                    } finally {
                        this.saving = false;
                    }
                },

                openEdit(file) {
                    this.errors = {};
                    this.editingId = file.id;
                    this.editingExtension = file.extension;
                    this.tagDraft = '';
                    // Set the parents first and the dependent lists after Alpine has drawn their options.
                    this.draft = Object.assign(blankDraft(), {
                        file_name: file.file_name, title: file.title, description: file.description || '',
                        category: file.category, period_type: file.period_type, tags: [...file.tags],
                    });
                    this.$nextTick(() => {
                        this.draft.item = file.item;
                        this.draft.period_value = file.period_value;
                        this.draft.region = file.region || '';
                    });
                    this.uploading = true;
                },

                async saveEdit() {
                    this.addTag();
                    this.saving = true;
                    try {
                        const { ok, status, result } = await this.api('PUT', this.fileUrl(this.editingId), this.draft);
                        if (status === 422) {
                            Object.entries(result.errors || {}).forEach(([name, messages]) => { this.errors[name] = messages[0]; });
                            return;
                        }
                        if (!ok) {
                            this.errors.general = 'Could not save (' + status + '). ' + (result.message || 'See storage/logs/laravel.log for details.');
                            return;
                        }
                        this.uploading = false;
                        this.editingId = null;
                        this.notify('File information saved.');
                        this.search(true);
                        this.loadSummary();
                    } catch (error) {
                        this.errors.general = 'Could not reach the server. Check that it is running and try again.';
                    } finally {
                        this.saving = false;
                    }
                },

                /** Soft delete: the file moves to "Deleted files" and an administrator can restore it. */
                async remove(file) {
                    if (!confirm('Delete "' + file.title + '"?\n\nIt moves to "Deleted files", where an administrator can restore it.')) return;
                    const { ok, result } = await this.api('DELETE', this.fileUrl(file.id));
                    if (!ok) { this.notify(result.message || 'Could not delete the file.'); return; }
                    this.selectedIds = this.selectedIds.filter(id => id !== file.id);
                    this.notify('File deleted. You can restore it from "Deleted files".');
                    this.search(true);
                    this.loadSummary();
                },

                async restore(file) {
                    const { ok, result } = await this.api('POST', this.fileUrl(file.id) + '/restore');
                    if (!ok) { this.notify(result.message || 'Could not restore the file.'); return; }
                    this.notify('File restored.');
                    this.search(true);
                    this.loadSummary();
                },

                toggleDeleted() {
                    this.showDeleted = !this.showDeleted;
                    this.selectedIds = [];
                    this.search(true);
                },

                // ----- Accounts (administrators) -----
                blankAccountForm() { return { open: false, id: null, name: '', username: '', password: '', role: 'client' }; },

                openAccounts() {
                    this.accountsOpen = true;
                    this.accountsTab = 'active';
                    this.accountForm = this.blankAccountForm();
                    this.accountErrors = {};
                    this.loadAccounts();
                },
                setAccountsTab(tab) {
                    this.accountsTab = tab;
                    this.accountForm = this.blankAccountForm();
                    return this.loadAccounts();
                },
                async loadAccounts() {
                    this.accountsLoading = true;
                    try {
                        const { ok, result } = await this.api('GET', this.urls.accounts + '?trashed=' + (this.accountsTab === 'deleted' ? 1 : 0));
                        if (!ok) { this.notify(result.message || 'Could not load the accounts.'); return; }
                        this.accounts = result.accounts;
                        this.accountCounts = result.counts;
                    } finally {
                        this.accountsLoading = false;
                    }
                },
                newAccount() {
                    this.accountErrors = {};
                    this.accountForm = Object.assign(this.blankAccountForm(), { open: true });
                },
                editAccount(account) {
                    this.accountErrors = {};
                    this.accountForm = { open: true, id: account.id, name: account.name, username: account.username, password: '', role: account.role };
                },
                async saveAccount() {
                    this.accountErrors = {};
                    this.savingAccount = true;
                    const editing = this.accountForm.id !== null;
                    const url = editing ? this.urls.accounts + '/' + this.accountForm.id : this.urls.accounts;
                    const body = { name: this.accountForm.name, username: this.accountForm.username, role: this.accountForm.role, password: this.accountForm.password };

                    try {
                        const { ok, status, result } = await this.api(editing ? 'PUT' : 'POST', url, body);
                        if (status === 422) {
                            Object.entries(result.errors || {}).forEach(([name, messages]) => { this.accountErrors[name] = messages[0]; });
                            return;
                        }
                        if (!ok) { this.accountErrors.general = result.message || 'Could not save the account (' + status + ').'; return; }
                        this.accountForm = this.blankAccountForm();
                        this.notify(editing ? 'Account updated.' : 'Account created.');
                        await this.loadAccounts();
                    } catch (error) {
                        this.accountErrors.general = 'Could not reach the server. Check that it is running and try again.';
                    } finally {
                        this.savingAccount = false;
                    }
                },
                async deleteAccount(account) {
                    if (!confirm('Delete the account of ' + account.name + ' (@' + account.username + ')?\n\nThey will no longer be able to log in. You can restore the account from the Deleted tab.')) return;
                    const { ok, result } = await this.api('DELETE', this.urls.accounts + '/' + account.id);
                    if (!ok) { this.notify(result.message || 'Could not delete the account.'); return; }
                    this.notify('Account deleted.');
                    this.loadAccounts();
                },
                async restoreAccount(account) {
                    const { ok, result } = await this.api('POST', this.urls.accounts + '/' + account.id + '/restore');
                    if (!ok) { this.notify(result.message || 'Could not restore the account.'); return; }
                    this.notify('Account restored.');
                    this.loadAccounts();
                },

                notify(message, milliseconds = 3200) {
                    this.toast = message;
                    setTimeout(() => { this.toast = ''; }, milliseconds);
                },
            };
        }
    </script>
</body>
</html>
