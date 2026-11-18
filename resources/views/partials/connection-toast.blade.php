@php
    $initialStatus = match ($dataSource) {
        'database' => [],
        'browser' => ['title' => 'Browser storage mode', 'description' => 'Records are saved in this browser. Use Export to keep a backup.', 'type' => 'info'],
        'sample' => ['title' => 'Sample data', 'description' => 'This page displays sample records for mapping.', 'type' => 'info'],
        default => null,
    };
    $hasActionFeedback = session()->has('success') || session()->has('error') || $errors->any();
@endphp

<div x-data="{
        notice: '',
        description: '',
        noticeType: 'success',
        timer: null,
        init() {
            const status = @js($initialStatus);
            if (status !== null && !@js($hasActionFeedback)) this.showStatus(status);
        },
        showStatus({ title = 'Database connected', description = 'Records displayed below are retrieved from the CBED database.', type = 'success' } = {}) {
            clearTimeout(this.timer);
            this.notice = title;
            this.description = description;
            this.noticeType = type;
            if (type !== 'error') this.timer = setTimeout(() => this.notice = '', 4500);
        },
        destroy() { clearTimeout(this.timer); }
    }"
    @cbed-data-status.window="showStatus($event.detail)">
    @include('partials.action-toast', ['isConnectionToast' => true])
</div>
