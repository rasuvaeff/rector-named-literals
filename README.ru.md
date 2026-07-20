# rasuvaeff/rector-named-literals

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/build.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/rector-named-literals/php)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![License](https://img.shields.io/packagist/l/rasuvaeff/rector-named-literals.svg)](LICENSE.md)
[English version](README.md)

Правило [Rector](https://getrector.com), нейтрализующее **boolean trap**: оно
добавляет имена параметров к литеральным аргументам, благодаря чему точки вызова
становятся самодокументируемыми.

```php
// до — что здесь значат true и false?
$mailer->send($message, true, false);

// после
$mailer->send($message, urgent: true, queue: false);
```

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно передать в контекст модели.

## Требования

- PHP 8.3+ для запуска правила
- `rector/rector` ^2.0

Для **обрабатываемого кода** достаточно PHP 8.0+ (именованные аргументы): правило
объявляет `MinPhpVersionInterface`, поэтому Rector автоматически пропускает его,
если целевая версия PHP в проекте ниже 8.0.

## Установка

```bash
composer require --dev rasuvaeff/rector-named-literals
```

## Использование

```php
// rector.php
use Rasuvaeff\RectorNamedLiterals\AddNameToLiteralArgumentRector;

return RectorConfig::configure()
    ->withRules([AddNameToLiteralArgumentRector::class]);        // bool literals only
```

Числовые и строковые литералы включаются опционально:

```php
->withConfiguredRule(AddNameToLiteralArgumentRector::class, [
    AddNameToLiteralArgumentRector::BOOL => true,     // default
    AddNameToLiteralArgumentRector::NUMERIC => true,  // 3, 2.5, -5
    AddNameToLiteralArgumentRector::STRING => true,   // 'linear'
])
```

Неизвестный ключ конфигурации или небулево значение приводит к ошибке прогона —
никакого тихого некорректного конфигурирования.

## Что именно делает (и от чего отказывается)

Учитывается семантика именованных аргументов: PHP запрещает позиционный аргумент
после именованного, поэтому, если совпавший литерал не является последним
аргументом, **каждый последующий позиционный аргумент также получает имя**:

```php
$task->run(true, $mode);          // → $task->run(force: true, mode: $mode);
```

Вызов остаётся нетронутым, если преобразование нельзя гарантированно считать
безопасным:

| Пропускаемый случай | Почему |
|---|---|
| Вызываемый объект объявлен в **интерфейсе** | реализации вправе переименовывать параметры — именованные аргументы сломали бы LSP-совместимые классы |
| `@no-named-arguments` на функции, классе или любом предке | автор явно исключил имена параметров из контракта |
| Распаковка аргументов (`...$args`) в вызове | позиционная арифметика статически неразрешима |
| Совпавшая позиция отображается на **variadic**-параметр | variadic-значения нельзя именовать |
| Вызываемый объект не разрешается через reflection | нет имён параметров, которые можно взять |
| First-class callable syntax (`foo(...)`) | это не вызов |
| **Встроенный** вызываемый объект, чьи планируемые имена не проходят проверку нативным reflection | карта сигнатур PHPStan конструирует фиксированную арность для variadic-встроенных (`min(arg1, arg2, …)`) — PHP отвергает такие имена в рантайме с «unknown named parameter». Каждое планируемое имя для встроенной функции предварительно сверяется с реальной сигнатурой |

Литералы `null` намеренно вне области действия — собственное правило Rector
`AddNameToNullArgumentRector` (из набора CodeQuality) уже покрывает их; запускайте
оба правила вместе для полного покрытия литералов.

### Чем отличается от `savinmikhail/AddNamedArgumentsRector`

Тот пакет именует **все** аргументы вызова (`str_contains(haystack: 'a',
needle: 'b')`) со стратегиями на каждый вызов. Это правило работает **на уровне
отдельного аргумента**: имена получают только литералы, переменные остаются
позиционными — diff затрагивает только те места, где вызов не читается.

## Предостережение

Добавление имени параметра делает это имя частью вашего compile-time контракта
с вызываемым объектом: если зависимость переименует параметр в минорном релизе,
ваш вызов сломается. Именно поэтому вызовы через интерфейс и объявления
`@no-named-arguments` пропускаются — но к вызовам стороннего vendor-кода
применяйте правило осознанно.

## Разработка

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```

E2e-сьют запускает настоящий бинарник `rector process` по файлам-фикстурам и
сравнивает результаты с зафиксированными файлами `.expected`.

## Лицензия

BSD-3-Clause.
