# rasuvaeff/ректор-литералы
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/rector-named-literals.svg)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/build.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/rector-named-literals/static-analysis.yml?branch=master)](https://github.com/rasuvaeff/rector-named-literals/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/rector-named-literals/php)](https://packagist.org/packages/rasuvaeff/rector-named-literals)
[![License](https://img.shields.io/packagist/l/rasuvaeff/rector-named-literals.svg)](LICENSE.md)
[English version](README.md)

A [Rector](https://getrector.com) rule that defuses the **boolean trap**: it
добавляет имена параметров к литеральным аргументам, поэтому сайты вызовов объясняют себя. @@ЛИНИЯ@@
```php
// before — what do true and false mean here?
$mailer->send($message, true, false);

// after
$mailer->send($message, urgent: true, queue: false);
```
> Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) имеет компактную ссылку
 > которую можно передать в качестве контекста. @@ЛИНИЯ@@
## Требования
- PHP 8.3+ для запуска правила
 - `rector/rector` ^2.0

 Для **обработанного кода** требуется только PHP 8.0+ (именованные аргументы): правило объявляет
 `MinPhpVersionInterface`, поэтому Rector автоматически пропускает его, когда целевая версия PHP
 вашего проекта ниже 8.0. @@ЛИНИЯ@@
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
Числовые и строковые литералы разрешены:
.
```php
->withConfiguredRule(AddNameToLiteralArgumentRector::class, [
    AddNameToLiteralArgumentRector::BOOL => true,     // default
    AddNameToLiteralArgumentRector::NUMERIC => true,  // 3, 2.5, -5
    AddNameToLiteralArgumentRector::STRING => true,   // 'linear'
])
```
Неизвестный ключ конфигурации или нелогическое значение приводит к сбою запуска — никаких негласных
 неправильных конфигураций. @@ЛИНИЯ@@
## Что именно он делает (и отказывается делать)
Семантика именованных аргументов учитывается: PHP запрещает использование позиционного аргумента после
 именованного аргумента, поэтому, если совпадающий литерал не является последним аргументом, **каждый
 следующий позиционный аргумент также именуется**:

```php
$task->run(true, $mode);          // → $task->run(force: true, mode: $mode);
```
Вызов остается нетронутым, если преобразование не является доказуемо безопасным:

 | Пропущенный случай | Почему |
 |---|---|
 | Вызываемый объект объявлен на **интерфейсе** | реализации могут законно переименовывать параметры — именованные аргументы нарушат работу LSP-совместимых классов |
 | `@no-named-arguments` для функции, класса или любого предка | автор явно исключил имена параметров из контракта |
 | Распаковка аргументов (`...$args`) в вызове | позиционная арифметика неразрешима статически |
 | Сопоставление позиций с параметром **variadic** | вариативные значения не могут быть названы |
 | Вызываемый объект не разрешим путем отражения | нет имен параметров |
 | Первоклассный вызываемый синтаксис (`foo(...)`) | не призыв |
 | **Встроенный** вызываемый абонент, чьи запланированные имена не соответствуют встроенному отражению | Карта сигнатур PHPStan изобретает варианты фиксированной арности для встроенных переменных с переменным числом значений (`min(arg1, arg2, …)`) — PHP отклоняет эти имена во время выполнения с «неизвестным именованным параметром». Каждое запланированное имя на встроенной памяти сначала подтверждается реальной подписью |

 `null` литералы намеренно выходят за рамки — собственный
 `AddNameToNullArgumentRector` Rector` (набор CodeQuality) уже охватывает их; запустите оба правила
 вместе для полного буквального покрытия. @@ЛИНИЯ@@
### Чем он отличается от `савинмихаил/AddNamedArgumentsRector`
Этот пакет именует **все** аргументы вызова (`str_contains(haystack: 'a',
 Needle: 'b')`) с помощью стратегий для каждого вызова. Это правило **для каждого аргумента**: только литералы
 получают имена, переменные остаются позиционными — разница затрагивает только те места
, где вызов не читается. @@ЛИНИЯ@@
## Предостережение
Добавление имени параметра делает это имя частью вашего контракта времени компиляции
 с вызываемым объектом: если зависимость переименовывает параметр в дополнительной версии,
 ваш вызов прерывается. Именно поэтому вызываемые интерфейсы и
 `@no-named-arguments` пропускаются, но для сторонних классов сознательно применяйте правило
 к вызовам поставщиков. @@ЛИНИЯ@@
## Разработка
```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
```
Пакет e2e запускает настоящий двоичный файл `rectorprocess` над файлами фикстур, и
 сравнивает результаты с зафиксированными аналогами `.expected`. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт.
