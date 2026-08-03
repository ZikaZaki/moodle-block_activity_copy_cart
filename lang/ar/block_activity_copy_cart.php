<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     block_activity_copy_cart
 * @category    string
 * @author      ZikaZaki <zika.github@gmail.com>
 * @copyright   2026 Numo <https://numo.sa>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'سلة نسخ الأنشطة';
$string['activity_copy_cart:addinstance'] = 'إضافة كتلة سلة نسخ الأنشطة';
$string['activity_copy_cart:myaddinstance'] = 'إضافة كتلة سلة نسخ الأنشطة إلى لوحة المعلومات';
$string['activity_copy_cart:copyactivities'] = 'نسخ الأنشطة إلى مقررات أخرى';
$string['nopermissions'] = 'ليس لديك صلاحية لنسخ الأنشطة من هذا المقرر.';
$string['clearcart'] = 'إفراغ الكل';
$string['cartempty'] = 'السلة فارغة. اسحب الأنشطة وأفلتها هنا.';
$string['copyactivities'] = 'نسخ الأنشطة';
$string['previewcopy'] = 'معاينة';
$string['addtocopycart'] = 'إضافة إلى سلة النسخ';

// صفحة اختيار المقررات الهدف.
$string['selectcoursestitle'] = 'اختيار المقررات المستهدفة';
$string['selectcourses'] = 'المقررات المراد النسخ إليها';
$string['cartsummary'] = 'الأنشطة المراد نسخها ({$a})';
$string['selectedcourses'] = 'المقررات المستهدفة ({$a})';
$string['cartexpired'] = 'انتهت صلاحية السلة أو أصبحت فارغة. يرجى إضافة الأنشطة إليها مرة أخرى.';
$string['cartinvalid'] = 'السلة المُرسلة غير صالحة.';
$string['notargetschosen'] = 'يرجى اختيار مقرر هدف واحد على الأقل.';
$string['badgerenamed'] = 'أُعيدت تسميته إلى: {$a}';
$string['backtoselection'] = 'العودة إلى اختيار المقرر';

// تنفيذ عملية النسخ.
$string['copyprogresstitle'] = 'جارٍ نسخ الأنشطة';
$string['copyprogressheading'] = 'اكتمل {$a->completedunits} من أصل {$a->totalunits} عملية نسخ';
$string['jobnotfound'] = 'تعذر العثور على مهمة النسخ هذه، أو انتهت صلاحيتها.';
$string['backtocourse'] = 'العودة إلى المقرر';
$string['logtargetcourse'] = 'المقرر المستهدف';
$string['logactivity'] = 'النشاط';
$string['logstatus'] = 'الحالة';
$string['logmessage'] = 'التفاصيل';
$string['statuspending'] = 'قيد الانتظار';
$string['statusrunning'] = 'قيد التنفيذ';
$string['statuscompleted'] = 'مكتمل';
$string['statuscompletedwitherrors'] = 'اكتمل مع وجود مشكلات';
$string['statusfailed'] = 'فشل';
$string['resultsuccess'] = 'تم النسخ';
$string['resultskipped'] = 'تم التخطي';
$string['resultfailed'] = 'فشل';
$string['skipsectionmissing'] = 'القسم المستهدف "{$a}" غير موجود في هذا المقرر.';
$string['skipnameconflict'] = 'يوجد بالفعل نشاط باسم "{$a}" في القسم المستهدف.';
$string['errorbackupfailed'] = 'فشلت عملية النسخ الاحتياطي لهذا النشاط، لذا تعذر نسخه إلى أي مقرر مستهدف.';
$string['errorrestoreprecheck'] = 'فشل الفحص المسبق للاستعادة: {$a}';
$string['errorrestorefailed'] = 'اكتملت عملية الاستعادة دون إنشاء نشاط جديد في المقرر المستهدف.';
$string['errorsourcecapabilitylost'] = 'لم تعد تملك صلاحية نسخ الأنشطة من المقرر المصدر، لذا تم إيقاف المهمة.';
$string['errortargetcapabilitylost'] = 'لم تعد تملك صلاحية النسخ إلى هذا المقرر.';
$string['copycompletedmessagesubject'] = 'اكتملت عملية نسخ النشاط';
$string['copycompletedmessagebody'] = 'اكتملت {$a->completedunits} من أصل {$a->totalunits} عملية نسخ، بالحالة: {$a->status}.';
$string['messageprovider:copycompleted'] = 'إشعار بانتهاء مهمة نسخ نشاط مُدرجة في قائمة الانتظار';

// الخصوصية.
$string['privacy:metadata:job'] = 'سجل لمهمة "نسخ الأنشطة" - الأنشطة المنسوخة، والمقررات المستهدفة، ومدى تقدم العملية.';
$string['privacy:metadata:job:userid'] = 'معرف المستخدم الذي بدأ مهمة النسخ.';
$string['privacy:metadata:job:sourcecourseid'] = 'المقرر الذي نُسخت منه الأنشطة.';
$string['privacy:metadata:job:cart'] = 'لقطة من الأنشطة وإعداداتها الخاصة بها وقت بدء المهمة.';
$string['privacy:metadata:job:targetcourseids'] = 'المقررات التي نُسخت الأنشطة إليها.';
$string['privacy:metadata:job:status'] = 'حالة تقدم المهمة.';
$string['privacy:metadata:job:timecreated'] = 'وقت بدء المهمة.';
$string['privacy:metadata:jobbackup'] = 'سجل للنسخة الاحتياطية الخاصة بعنصر واحد من عناصر السلة ضمن مهمة النسخ، تُستخدم لكل مقرر هدف يُنسخ إليه هذا العنصر.';
$string['privacy:metadata:jobbackup:jobid'] = 'مهمة النسخ التي تنتمي إليها هذه النسخة الاحتياطية.';
$string['privacy:metadata:jobbackup:sourcecmid'] = 'النشاط الذي تم نسخه احتياطيًا.';
$string['privacy:metadata:jobbackup:status'] = 'حالة النسخة الاحتياطية نفسها.';

// شجرة المقررات المستهدفة.
$string['searchcourses'] = 'البحث عن مقرر';
$string['nosearchresults'] = 'لم يتم العثور على مقررات مطابقة.';
$string['nocategoriesavailable'] = 'لا توجد تصنيفات متاحة.';
$string['nocoursesavailable'] = 'لا توجد مقررات متاحة في هذا التصنيف.';
$string['expandcategory'] = 'توسيع التصنيف';
$string['noscript'] = 'تتطلب هذه الميزة تفعيل JavaScript في متصفحك.';

// نافذة إعدادات العنصر.
$string['settings'] = 'إعدادات نسخ النشاط';

// مجموعة تفاصيل النشاط.
$string['settingsgeneral'] = 'عام';
$string['renameactivity'] = 'اسم النشاط';
$string['rename_info'] = 'الاسم الذي سيحمله النشاط المنسوخ في كل مقرر مستهدف.';
$string['nameconflict'] = 'تعارض أسماء الأنشطة';
$string['nameconflict_info'] = 'الإجراء المتبع في حال وجود نشاط بهذا الاسم مسبقًا في القسم المستهدف.';
$string['resolveconflict'] = 'إعادة تسمية تلقائية';
$string['skipactivity'] = 'تخطي النسخ';
$string['targetvisibility'] = 'ظهور النشاط';
$string['visibility_info'] = 'ما إذا كان النشاط المنسوخ ظاهرًا أو مخفيًا للطلاب في المقرر المستهدف.';
$string['visibilitysource'] = 'مطابق للنشاط المصدر';
$string['visibilityshow'] = 'إظهار';
$string['visibilityhide'] = 'إخفاء';
$string['settingsadvanced'] = 'إعدادات متقدمة';
$string['groupcontentdata'] = 'محتوى النشاط وبياناته';
$string['contentdata_info'] = 'قد تشير قيود الوصول إلى تواريخ أو مجموعات أو درجات غير موجودة في المقرر المستهدف.';
$string['keeprestrictions'] = 'الإبقاء على قيود الوصول (التواريخ، المجموعات، شروط الدرجات، وغيرها)';

// مجموعة تفاصيل القسم المستهدف.
$string['settingsplacement'] = 'الموضع';
$string['targetsection'] = 'اسم القسم';
$string['section_info'] = 'القسم الذي ينتمي إليه هذا النشاط حاليًا. يُستخدم كمرجع لتحديد القسم المكافئ في كل مقرر مستهدف.';
$string['matchsectionby'] = 'مطابقة القسم حسب';
$string['sectionmatch_info'] = 'المطابقة حسب الموضع أكثر موثوقية من المطابقة حسب الاسم عندما تختلف أسماء الأقسام أو تواريخها بين المقررات المستهدفة.';
$string['matchbyname'] = 'الاسم';
$string['matchbyposition'] = 'الموضع (الرقم)';
$string['sectionmissing'] = 'القسم المستهدف غير موجود';
$string['sectionmissing_info'] = 'الإجراء المتبع في حال عدم وجود هذا القسم بعد في المقرر المستهدف.';
$string['createnewsection'] = 'إنشاء تلقائي';

// التحقق من صحة نافذة إعدادات العنصر.
$string['error_sectionrequired'] = 'القسم المستهدف مطلوب.';
$string['error_sectionmatchrequired'] = 'اختر طريقة مطابقة القسم.';
$string['error_sectionmissingrequired'] = 'اختر الإجراء المتبع عند عدم وجود القسم المستهدف.';
$string['error_nameconflictrequired'] = 'اختر طريقة التعامل مع تعارض الأسماء.';
$string['error_visibilityrequired'] = 'اختر خيار الظهور.';
$string['error_summaryheading'] = 'يرجى تصحيح ما يلي:';
