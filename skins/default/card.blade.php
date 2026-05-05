@php
  $card = $oembed_card ?? [];
  $url = $card['url'] ?? '';
  $title = $card['title'] ?? '';
  $description = $card['description'] ?? '';
  $image = $card['image'] ?? '';
  $source = $card['source'] ?? '';
@endphp
<figure contenteditable="false">
  @if ($image !== '')
    <img src="{{ $image }}" alt="" loading="lazy" />
  @endif
  <figcaption>
    <h3>
      <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $title }}</a>
    </h3>
    @if ($description !== '')
      <p>{{ $description }}</p>
    @endif
    @if ($source !== '')
      <cite>{{ $source }}</cite>
    @endif
  </figcaption>
</figure>
