@if (session('status'))
    <div class="border-l-4 border-[#1746d1] bg-blue-50 px-4 py-3 text-sm font-medium text-blue-950" role="status">
        {{ session('status') }}
    </div>
@endif
