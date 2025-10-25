@component('mail::message')
# {{ __('Upload partially completed') }}

{{ __('Your file was processed, but some records could not be imported. Please download the attached CSV file to view the errors.') }}

**{{ __('Batch ID') }}:** {{ $batchId }}

@if(!empty($filename))
**{{ __('Attached file') }}:** {{ $filename }}
@endif

{{ __('You can fix the issues and try uploading the corrected file again.') }}

@endcomponent
