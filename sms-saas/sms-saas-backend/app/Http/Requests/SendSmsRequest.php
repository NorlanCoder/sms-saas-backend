<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendSmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required'],
            'message' => ['required', 'string', 'min:1', 'max:160'],
            'sender_id' => ['required', 'string', 'max:11'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $to = $this->input('to');

            if (is_string($to)) {
                if (! preg_match('/^\+[1-9]\d{6,14}$/', $to)) {
                    $validator->errors()->add('to', 'Le numéro de destinataire doit être au format international et commencer par +');
                }

                return;
            }

            if (is_array($to)) {
                if (count($to) > 1000) {
                    $validator->errors()->add('to', 'Le nombre maximal de destinataires est de 1000');
                }

                foreach ($to as $index => $recipient) {
                    if (! is_string($recipient) || ! preg_match('/^\+[1-9]\d{6,14}$/', $recipient)) {
                        $validator->errors()->add(
                            'to.'.$index,
                            'Le numéro de destinataire doit être au format international et commencer par +'
                        );
                    }
                }

                return;
            }

            $validator->errors()->add('to', 'Le champ to doit être une chaîne ou un tableau de numéros');
        });
    }
}
