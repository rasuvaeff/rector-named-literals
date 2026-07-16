# rasuvaeff/rector-named-literals

[English version](README.md)

Rector rule `AddNameToLiteralArgumentRector` устраняет boolean trap, добавляя
имена parameters к literal arguments. `bool` включён по умолчанию; numeric и
string literals opt-in. `null` намеренно вне scope: его обрабатывает core
`AddNameToNullArgumentRector`.

## Требования

- PHP 8.3+;
- `rector/rector` `^2.0`.

## Установка

```bash
composer require --dev rasuvaeff/rector-named-literals
```

## Использование

```php
use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;

return RectorConfig::configure()
    ->withRules([AddNameToLiteralArgumentRector::class]);
```

Constants `BOOL`, `NUMERIC` и `STRING` выбирают виды literal. Rule именует
аргумент только когда reflection подтверждает contract и generated call будет
valid PHP.

## Что именно делает и от чего отказывается

Rule пропускает interface callees, `@no-named-arguments`, unpacking, variadic
targets и ambiguous calls. После named argument все последующие positional
arguments также должны быть named; иначе call остаётся без изменений.

### Отличие от `savinmikhail/AddNamedArgumentsRector`

Пакет специально ограничен literals и предпочитает compatibility широте
transformation. Он не именует `null` и не меняет calls без надёжного contract.

## Caveat

Named arguments зависят от parameter names dependency API. Проверяйте diff и
запускайте application tests при обновлении dependencies.

## Разработка

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
