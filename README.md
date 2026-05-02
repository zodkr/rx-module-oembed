# rx-module-oembed

Rhymix 용 oEmbed 모듈. URL 을 임베드 가능한 콘텐츠(이미지·동영상·플레이어 등)로 변환합니다.

## 설치

이 저장소는 [zodkr/core](https://github.com/zodkr/core) 의 git submodule 로 사용됩니다.

```sh
git submodule add -b dev https://github.com/zodkr/rx-module-oembed.git modules/oembed
```

또는 부모 저장소 clone 시:

```sh
git clone --recurse-submodules https://github.com/zodkr/core.git
```

## 구조

PSR-4 (`Rhymix\Modules\Oembed\*`).

```
modules/oembed/
├── conf/        info.xml, module.xml
├── controllers/
├── models/
├── providers/   {name}/provider.php
└── views/
```

## 라이선스

MIT — [LICENSE](LICENSE) 참고.
