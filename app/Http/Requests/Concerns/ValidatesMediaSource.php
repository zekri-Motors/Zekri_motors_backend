<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

trait ValidatesMediaSource
{
    /**
     * Exactly one of `file` / `url` must be present, and `type` is
     * required when it's an external url (a real uploaded file's type can
     * be guessed from its mime type instead).
     */
    protected function ensureExactlyOneMediaSource(Validator $validator): void
    {
        $hasFile = $this->hasFile('file');
        $hasUrl = filled($this->input('url'));

        if ($hasFile === $hasUrl) {
            $validator->errors()->add('file', $hasFile
                ? 'أدخل إما ملف أو رابط خارجي للميديا، وليس الاثنين معًا'
                : 'يجب إدخال ملف أو رابط خارجي للميديا');

            return;
        }

        if ($hasUrl && blank($this->input('type'))) {
            $validator->errors()->add('type', 'نوع الميديا (image أو video) إلزامي عند استخدام رابط خارجي');
        }
    }
}
