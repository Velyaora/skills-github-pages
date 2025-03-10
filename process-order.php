<?php
// ✅ تفعيل CORS للسماح بطلبات من `payment.html`
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

try {
    // ✅ استقبال بيانات الطلب من `payment.html`
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        throw new Exception("❌ No data received");
    }

    // ✅ التحقق من البيانات المطلوبة
    $requiredFields = ["orderID", "fullName", "country", "city", "address", "postalCode", "phone", "totalPrice", "productID", "quantity"];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("❌ Missing field: $field");
        }
    }

    // ✅ استخراج البيانات من الطلب
    $orderID = htmlspecialchars(strip_tags($data["orderID"]));
    $fullName = htmlspecialchars(strip_tags($data["fullName"]));
    $country = htmlspecialchars(strip_tags($data["country"]));
    $city = htmlspecialchars(strip_tags($data["city"]));
    $address = htmlspecialchars(strip_tags($data["address"]));
    $postalCode = htmlspecialchars(strip_tags($data["postalCode"]));
    $phone = htmlspecialchars(strip_tags($data["phone"]));
    $totalPrice = htmlspecialchars(strip_tags($data["totalPrice"]));
    $productID = htmlspecialchars(strip_tags($data["productID"]));
    $quantity = htmlspecialchars(strip_tags($data["quantity"]));

    // ✅ إنشاء ملف CSV يومي لتخزين الطلبات الجديدة فقط
    $date = date("Y-m-d");
    $fileName = "orders-$date.csv"; // مثال: orders-2024-03-08.csv

    // ✅ فتح الملف وإضافة الطلب الجديد
    $file = fopen($fileName, "a");
    if (!$file) {
        throw new Exception("❌ Failed to open CSV file for writing.");
    }

    // ✅ إذا كان الملف جديدًا، أضف العناوين
    if (filesize($fileName) == 0) {
        fputcsv($file, ["Order ID", "Full Name", "Country", "City", "Address", "Postal Code", "Phone", "Total Price", "Product ID", "Quantity"]);
    }

    // ✅ إضافة الطلب الجديد
    fputcsv($file, [$orderID, $fullName, $country, $city, $address, $postalCode, $phone, $totalPrice, $productID, $quantity]);
    fclose($file);

    // ✅ تسجيل الطلب في السجل لمراقبة العمليات
    error_log("✅ Order saved in CSV: " . json_encode($data));

    // ✅ إعداد البيانات لإرسالها إلى Telegram
    $telegramBotToken = "6961886563:AAHZwl-UaAWaGgXwzyp1vazRu1Hf37FKX2A"; // 🔹 استبدل بالتوكن الحقيقي
    $telegramChatID = "-1002290156309"; // 🔹 استبدل بمعرف الشات
    $message = "📦 *New Order Received!* 🚀\n\n" .
               "🆔 *Order ID:* $orderID\n" .
               "👤 *Name:* $fullName\n" .
               "📍 *Country:* $country\n" .
               "🏙️ *City:* $city\n" .
               "📌 *Address:* $address\n" .
               "📬 *Postal Code:* $postalCode\n" .
               "📞 *Phone:* $phone\n" .
               "🛒 *Product ID:* $productID\n" .
               "🔢 *Quantity:* $quantity\n" .
               "💰 *Total Paid:* $totalPrice USD";

    // ✅ إرسال البيانات إلى Telegram باستخدام cURL لضمان نجاح الاتصال
    $telegramURL = "https://api.telegram.org/bot$telegramBotToken/sendMessage";
    $postData = [
        "chat_id" => $telegramChatID,
        "text" => $message,
        "parse_mode" => "Markdown"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $telegramURL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $telegramResponse = curl_exec($ch);
    curl_close($ch);

    // ✅ التحقق من نجاح الإرسال إلى تيليجرام
    if (!$telegramResponse) {
        throw new Exception("❌ Failed to send message to Telegram.");
    }

    // ✅ تسجيل استجابة API تيليجرام
    error_log("📤 Telegram API Response: " . $telegramResponse);

    // ✅ إرسال رد JSON عند نجاح العملية
    echo json_encode(["success" => true, "message" => "✅ Order successfully saved in CSV and sent to Telegram"]);

} catch (Exception $e) {
    error_log("❌ Error in process-order.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
