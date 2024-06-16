<?php

namespace Wyox\GitlabReport\Components;

class TraceComponent extends MarkdownComponent
{
    public function render(): string
    {
        $exception = $this->incident->exception;

        return <<<EOF
**Trace**
```php
{$exception->getTraceAsString()}
```


EOF;
    }
}
