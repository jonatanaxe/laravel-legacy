{{-- Own pagination: the default links() emits Bootstrap markup, which we no longer load. --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        <span class="pager-info">page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="is-disabled" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
