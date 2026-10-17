<?php

declare(strict_types=1);

/**
 * Spartan core messages (Arabic). See lang/en/spartan.php for overriding.
 */
return [
    'validation' => [
        'required'    => 'حقل :field مطلوب.',
        'string'      => 'يجب أن يكون حقل :field نصاً.',
        'integer'     => 'يجب أن يكون حقل :field عدداً صحيحاً.',
        'boolean'     => 'يجب أن تكون قيمة حقل :field صحيحة أو خاطئة.',
        'numeric'     => 'يجب أن يكون حقل :field رقماً.',
        'email'       => 'يجب أن يكون حقل :field عنوان بريد إلكتروني صالحاً.',
        'url'         => 'يجب أن يكون حقل :field رابطاً صالحاً.',
        'date'        => 'يجب أن يكون حقل :field تاريخاً صالحاً (YYYY-MM-DD).',
        'alpha'       => 'يجب أن يحتوي حقل :field على حروف فقط.',
        'alpha_num'   => 'يجب أن يحتوي حقل :field على حروف وأرقام فقط.',
        'confirmed'   => 'تأكيد حقل :field غير مطابق.',
        'min_numeric' => 'يجب ألا تقل قيمة :field عن :min.',
        'min_string'  => 'يجب ألا يقل طول :field عن :min حرفاً.',
        'max_numeric' => 'يجب ألا تتجاوز قيمة :field :max.',
        'max_string'  => 'يجب ألا يتجاوز طول :field :max حرفاً.',
        'in'          => 'يجب أن تكون قيمة :field واحدة من: :values.',
        'regex'       => 'صيغة حقل :field غير صالحة.',
        'unique'      => 'قيمة :field مستخدمة من قبل.',
        'invalid'     => 'البيانات المرسلة غير صالحة.',
    ],

    'attributes' => [],

    'http' => [
        'unauthorized'       => 'غير مصرح.',
        'login_required'     => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.',
        'forbidden'          => 'ممنوع.',
        'forbidden_page'     => 'ليست لديك الصلاحيات اللازمة للوصول إلى هذه الصفحة.',
        'action_unauthorized' => 'غير مصرح لك بتنفيذ هذا الإجراء.',
        'not_found'          => 'الصفحة المطلوبة غير موجودة.',
        'csrf'               => 'رمز الحماية (CSRF) غير صالح أو منتهي الصلاحية.',
        'csrf_page'          => 'رمز الحماية غير صالح أو منتهي الصلاحية. يرجى إعادة تحميل الصفحة والمحاولة مرة أخرى.',
        'too_many_requests'  => 'طلبات كثيرة جداً. يرجى المحاولة لاحقاً.',
        'server_error'       => '500 خطأ داخلي في الخادم',
        'server_error_page'  => 'حدث خطأ غير متوقع.',
        'title_403'          => '403 ممنوع',
        'title_429'          => '429 طلبات كثيرة جداً',
        'title_500'          => '500 خطأ داخلي في الخادم',
    ],
];
