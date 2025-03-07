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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dsersOrderData));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ✅ التحقق من نجاح العملية
    $dsersResponse = json_decode($response, true);
    if (!$dsersResponse || $httpCode !== 200 || !isset($dsersResponse["success"]) || !$dsersResponse["success"]) {
        throw new Exception("❌ DSers API request failed. Response: " . json_encode($dsersResponse));
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

    // ✅ تسجيل الطلب في قاعدة البيانات (اختياري)
    $dbHost = "localhost";
    $dbUser = "root";
    $dbPass = "password";
    $dbName = "orders_db";

    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        throw new Exception("⚠️ Database connection failed");
    }

    $stmt = $conn->prepare("INSERT INTO orders (order_id, full_name, country, city, address, postal_code, phone, total_price, product_id, quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $orderID, $fullName, $country, $city, $address, $postalCode, $phone, $totalPrice, $productID, $quantity);
    $stmt->execute();
    $stmt->close();
    $conn->close();

    // ✅ إرسال رد JSON عند نجاح العملية
    echo json_encode(["success" => true, "message" => "✅ Order successfully processed in DSers"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
