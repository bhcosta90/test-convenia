@component('mail::message')
# {{ __('Upload partially completed') }}

{{ __('Your file was processed, but some records could not be imported. See the error details below.') }}

**{{ __('Batch ID') }}:** {{ $batchId }}

{{ __('Errors (JSON)') }}:

```json
{!! $errorsJson !!}
```

{{ __('You can fix the issues and try uploading the corrected file again.') }}

@endcomponent
