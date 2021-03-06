<article @php(post_class())>
  <header class="entry-header">
    <h1 class="entry-title">{{ get_the_title() }}</h1>
    @php(the_excerpt())
  </header>
  <div class="swagpath-content">
    <div class="swagifacts">
      <ul>
        @foreach(SingleSwagpath::get_swagpath_swagifacts() as $swagifact)
          <li class="swagpath-swagifact {{ $swagifact['slug'] === $current_swagifact ? 'is-current' : '' }} {{ $swagifact['is_completed'] ? 'is-complete' : '' }}">
            <a href="{{ SingleSwagpath::get_swagifact_permalink($swagifact['slug'], $post) }}">
            @if($swagifact['is_completed'])
              <i class="fas fa-check-circle"></i>
            @endif
            Section {{ $loop->iteration }}</a>
            <div class="section-name">{{ $swagifact['title'] }}</div>
          </li>
        @endforeach
      </ul>
    </div>
    <div class="current-swagifact">
        @if($current_swagifact)
        {!! SingleSwagpath::do_swagifact($current_swagifact) !!}
        @else
        No swagifacts in path
        @endif
    </div>
  </div>
  <footer>
    {!! wp_link_pages(['echo' => 0, 'before' => '<nav class="page-nav"><p>' . __('Pages:', 'sage'), 'after' => '</p></nav>']) !!}
  </footer>
</article>
