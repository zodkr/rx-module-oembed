@php
  $card = $oembed_card ?? [];
  $url = $card['url'] ?? '';
  $title = $card['title'] ?? '';
  $description = $card['description'] ?? '';
  $image = $card['image'] ?? '';
  $source = $card['source'] ?? '';
@endphp
<figure class="oembed-card" contenteditable="false">
  @if ($image !== '')
    <img class="oembed-card__thumb" src="{{ $image }}" alt="" loading="lazy" />
  @endif
  <figcaption class="oembed-card__body">
    <h3 class="oembed-card__title">
      <a class="oembed-card__link" href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $title }}</a>
    </h3>
    @if ($description !== '')
      <p class="oembed-card__desc">{{ $description }}</p>
    @endif
    @if ($source !== '')
      <cite class="oembed-card__source">{{ $source }}</cite>
    @endif
  </figcaption>
</figure>
