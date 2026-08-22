<?php

declare(strict_types=1);

/**
 * Arabic language file.
 *
 * Usage in views:   @lang('app.welcome', ['name' => $user->name])
 * Usage in PHP:     trans('app.welcome', ['name' => $user->name])
 */
return [
    'app_name' => 'سبارتن',

    'welcome' => 'أهلاً بك، :name!',
    'goodbye' => 'مع السلامة، :name. نراك قريباً!',

    'nav' => [
        'home'       => 'الرئيسية',
        'dashboard'  => 'لوحة التحكم',
        'courses'    => 'الكورسات',
        'calendar'   => 'التقويم',
        'settings'   => 'الإعدادات',
        'logout'     => 'تسجيل خروج',
        'login'      => 'تسجيل دخول',
        'register'   => 'إنشاء حساب',
    ],

    'actions' => [
        'save'    => 'حفظ',
        'cancel'  => 'إلغاء',
        'delete'  => 'حذف',
        'edit'    => 'تعديل',
        'create'  => 'إنشاء',
        'update'  => 'تحديث',
        'confirm' => 'تأكيد',
        'back'    => 'رجوع',
        'submit'  => 'إرسال',
        'search'  => 'بحث',
    ],

    'messages' => [
        'saved'   => 'تم حفظ التغييرات بنجاح.',
        'deleted' => 'تم حذف السجل بنجاح.',
        'error'   => 'حدث خطأ. يرجى المحاولة مرة أخرى.',
        'not_found' => 'المورد المطلوب غير موجود.',
        'unauthorized' => 'يجب تسجيل الدخول للوصول إلى هذه الصفحة.',
        'forbidden' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء.',
    ],

    'pagination' => [
        'previous' => '« السابق',
        'next'     => 'التالي »',
        'showing'  => 'عرض :from إلى :to من أصل :total نتيجة',
    ],
];
