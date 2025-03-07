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

    // ✅ بيانات API DSers
    $dsersApiUrl = "https://api.dsers.com/v1/order/create";
    $dsersApiKey = "YOUR_DSERS_API_KEY"; // 🔹 استبدل بمفتاح DSers الحقيقي

    // ✅ تجهيز البيانات لإرسالها إلى DSers
    $dsersOrderData = [
        "order_id" => $orderID,
        "customer_name" => $fullName,
        "country" => $country,
        "city" => $city,
        "address" => $address,
        "postal_code" => $postalCode,
        "phone" => $phone,
        "total_price" => $totalPrice,
        "items" => [
            [
                "product_id" => $productID,
                "quantity" => $quantity
            ]
        ]
    ];

    // ✅ إرسال الطلب إلى DSers عبر `cURL`
    $ch = curl_init($dsersApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $dsersApiKey"
    ]);

    // ✅ تحويل البيانات إلى JSON والتأكد من نجاح العملية
    $jsonData = json_encode($dsersOrderData);
    if ($jsonData === false) {
        echo json_encode(["success" => false, "message" => "❌ JSON encoding failed"]);
        exit;
    }

    // ✅ إرسال الطلب إلى DSers API
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch); // 🔹 التقاط أي خطأ في cURL
    curl_close($ch);

    // ✅ تسجيل الأخطاء في السجل
    error_log("DSers API HTTP Code: " . $httpCode);
    error_log("DSers API Response: " . json_encode($response));
    if ($curlError) {
        error_log("cURL Error: " . $curlError);
    }

    // ✅ تحليل استجابة DSers
    $dsersResponse = json_decode($response, true);
    error_log("DSers Parsed Response: " . json_encode($dsersResponse)); // ✅ تسجيل استجابة DSers بعد فك التشفير

    // ✅ التحقق من نجاح العملية
    if (!$dsersResponse || $httpCode !== 200 || !isset($dsersResponse["success"]) || !$dsersResponse["success"]) {
        echo json_encode([
            "success" => false,
            "message" => "❌ DSers API request failed",
            "http_code" => $httpCode,
            "curl_error" => $curlError,
            "response" => $response
        ]);
        exit;
    }

    // ✅ إرسال إشعار إلى Telegram عند نجاح الطلب
    $telegramBotToken = "6961886563:AAHZwl-UaAWaGgXwzyp1vazRu1Hf37FKX2A"; // 🔹 استبدل بتوكن تيليجرام الحقيقي
    $telegramChatID = "-1002290156309"; // 🔹 استبدل بمعرف الشات
    $message = "📦 *New Order Processed in DSers!*\n\n" .
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

    file_get_contents("https://api.telegram.org/bot$telegramBotToken/sendMessage?chat_id=$telegramChatID&text=" . urlencode($message) . "&parse_mode=Markdown");

    // ✅ إنشاء ملف CSV يومي لتخزين الطلبات الجديدة فقط
    $date = date("Y-m-d"); // ✅ تاريخ اليوم
    $fileName = "orders-$date.csv"; // ✅ مثال: orders-2024-03-08.csv

    // ✅ فتح الملف وإضافة الطلب الجديد
    $file = fopen($fileName, "a");

    // ✅ إذا كان الملف جديدًا، أضف العناوين
    if (filesize($fileName) == 0) {
        fputcsv($file, ["Order ID", "Full Name", "Country", "City", "Address", "Postal Code", "Phone", "Total Price", "Product ID", "Quantity"]);
    }

    // ✅ إضافة الطلب الجديد
    fputcsv($file, [$orderID, $fullName, $country, $city, $address, $postalCode, $phone, $totalPrice, $productID, $quantity]);
    fclose($file);

    // ✅ إرسال رد JSON عند نجاح العملية
    echo json_encode(["success" => true, "message" => "✅ Order successfully processed in DSers"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
