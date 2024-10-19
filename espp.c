#include <WiFi.h>
#include <WebServer.h>
#include <DNSServer.h>
#include <WiFiManager.h>
#include <Firebase_ESP_Client.h>
#include <addons/TokenHelper.h>
#include <addons/RTDBHelper.h>
#include <queue>

#define API_KEY "AIzaSyD_5JkJaZr60O2FZ80H84HL9u6lAjgrZWI"
#define DATABASE_URL "https://dbvending-1b336-default-rtdb.firebaseio.com"

FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;

WiFiManager wifiManager;
WebServer server(80);

unsigned long lastUpdateTime = 0;
const unsigned long updateInterval = 5000; // Check for updates every 5 seconds

unsigned long lastPresenceUpdateTime = 0;
const unsigned long presenceUpdateInterval = 3000; // Update presence every 3 seconds
int presenceCounter = 0;

struct Product {
  String id;
  String name;
  float price;
  int quantity;
  bool changed;  // New field to track changes
};

Product products[2] = {
  {"-O8GSb1L_mn9CLR1Z0Ly", "", 0, 0, false},
  {"-O8GSldDvFUT5olxHCiX", "", 0, 0, false}
};

bool isConnectedToWiFi = false;
bool isConnectedToFirebase = false;

bool wasDisconnected = false;

// Add these global variables at the top of your file
bool resetPending = false;
String pendingResetData = "";

std::queue<String> transactionQueue;

// Function prototypes
void updateStatusInFirebase();
bool updateProductData();
void sendUpdateToArduino();
void updateStockInFirebase(String data);
bool checkArduinoConnection();
void updateESP32Presence();
void handleRoot();

bool checkArduinoConnection() {
  // Implement the actual check here. For now, we'll assume it's always connected.
  return true;
}

bool checkConnection() {
  bool currentlyConnected = (WiFi.status() == WL_CONNECTED) && Firebase.ready();
  
  if (!currentlyConnected) {
    wasDisconnected = true;
  }
  
  return currentlyConnected;
}


void updateESP32Presence() {
  presenceCounter = (presenceCounter % 5) + 1;
  if (Firebase.RTDB.setInt(&fbdo, "esp32_presence", presenceCounter)) {
    Serial.println("esp32_presence: " + String(presenceCounter));
  }
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("ESP32 Starting Up");
  Serial.println("//");

  // WiFiManager
  wifiManager.autoConnect("HygienexCare_AP", "password");

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("Connected to WiFi");
    isConnectedToWiFi = true;
    Serial.print("ESP32 IP address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("Failed to connect to WiFi");
    return;
  }
  
  Serial.print("Connecting to Firebase ...");
  config.api_key = API_KEY;
  config.database_url = DATABASE_URL;
  
  if (Firebase.signUp(&config, &auth, "", "")) {
    Firebase.begin(&config, &auth);
    Firebase.reconnectWiFi(true);
    Serial.println("Firebase Connected!");
    isConnectedToFirebase = true;
  } else {
    Serial.println("\nFirebase connection failed");
    return;
  }
  Serial.println("//");

  Serial.print("Connecting to Mega ...");
  if (checkArduinoConnection()) {
    Serial.println("Mega Connected!");
  } else {
    Serial.println("\nMega connection failed");
    return;
  }
  Serial.println("//");

  Serial.print("Updating the Product ...");
  if (updateProductData()) {
    Serial.println("Product Updated!");
  } else {
    Serial.println("\nNo product updates needed");
  }
  Serial.println("//");

  server.on("/", handleRoot);
  server.begin();
  Serial.println("HTTP server started");

  Serial.println("Then Start Counting ...");
}

void loop() {
  unsigned long currentMillis = millis();
  
  if (currentMillis - lastPresenceUpdateTime > presenceUpdateInterval) {
    lastPresenceUpdateTime = currentMillis;
    updateESP32Presence();
  }

  bool connectionStatus = checkConnection();
  
  if (connectionStatus) {
    if (wasDisconnected) {
      Serial.println("DEBUG: Connection regained, requesting current stock");
      requestCurrentStock();
      wasDisconnected = false;
      lastUpdateTime = currentMillis;
    }
    
    // Process any pending transactions in the queue
    while (!transactionQueue.empty()) {
      Serial.println("DEBUG: Processing queued transaction");
      String transactionData = transactionQueue.front();
      if (transactionData.startsWith("TRANSACTION")) {
        Serial.println("DEBUG: Retrying queued transaction");
        // Parse and retry adding the transaction
        // ... (implement this part)
      } else {
        updateStockInFirebase(transactionData);
      }
      transactionQueue.pop();
    }
  } else {
    wasDisconnected = true;
  }

  if (Serial.available() > 0) {
    String data = Serial.readStringUntil('\n');
    Serial.println("DEBUG: Received data from Arduino: " + data);
    if (data.startsWith("STOCK_UPDATE")) {
      Serial.println("DEBUG: Processing stock update");
      updateStockInFirebase(data);
    } else if (data.startsWith("CURRENT_STOCK")) {
      Serial.println("DEBUG: Processing current stock update");
      handleCurrentStock(data);
      lastUpdateTime = currentMillis;
    } else if (data.startsWith("RESET_DATA")) {
      Serial.println("DEBUG: Processing reset data");
      handleResetData(data);
    }
  }

  // Only check for updates if we're not waiting for current stock data
  if (connectionStatus && !wasDisconnected && currentMillis - lastUpdateTime > updateInterval) {
    if (updateProductData()) {
      Serial.println("//");
      Serial.println("START_UPDATE");
      sendUpdateToArduino();
      Serial.println("END_UPDATE");
      Serial.println("//");
    }
    lastUpdateTime = currentMillis;
  }

  server.handleClient();
  delay(10);
}

bool updateProductData() {
  bool anyChanges = false;
  for (int i = 0; i < 2; i++) {
    products[i].changed = false;  // Reset change flag
    if (Firebase.RTDB.getString(&fbdo, "/tables/products/" + products[i].id + "/product_name")) {
      String newName = fbdo.stringData();
      if (newName != products[i].name) {
        products[i].name = newName;
        products[i].changed = true;
        anyChanges = true;
      }
    }
    if (Firebase.RTDB.getFloat(&fbdo, "/tables/products/" + products[i].id + "/product_price")) {
      float newPrice = fbdo.floatData();
      if (newPrice != products[i].price) {
        products[i].price = newPrice;
        products[i].changed = true;
        anyChanges = true;
      }
    }
    if (Firebase.RTDB.getInt(&fbdo, "/tables/products/" + products[i].id + "/product_quantity")) {
      int newQuantity = fbdo.intData();
      if (newQuantity != products[i].quantity) {
        products[i].quantity = newQuantity;
        products[i].changed = true;
        anyChanges = true;
      }
    }
  }
  return anyChanges;
}

void sendUpdateToArduino() {
  String updateString = "U,";
  for (int i = 0; i < 2; i++) {
    if (products[i].changed) {
      updateString += products[i].name + "," + String(products[i].price, 2) + "," + String(products[i].quantity);
    } else {
      updateString += "NC,NC,NC";
    }
    if (i == 0) updateString += ",";
  }
  Serial.println(updateString);
}

void updateStockInFirebase(String data) {
  Serial.println("DEBUG: Entering updateStockInFirebase");
  // Parse the data
  int firstComma = data.indexOf(',');
  int secondComma = data.indexOf(',', firstComma + 1);
  
  int productIndex = data.substring(firstComma + 1, secondComma).toInt();
  int newStock = data.substring(secondComma + 1).toInt();

  Serial.println("DEBUG: Product Index: " + String(productIndex) + ", New Stock: " + String(newStock));

  // Calculate the quantity dispensed
  int quantityDispensed = products[productIndex].quantity - newStock;
  Serial.println("DEBUG: Quantity Dispensed: " + String(quantityDispensed));

  // Update the local product data
  products[productIndex].quantity = newStock;

  // Update Firebase stock
  String productId = products[productIndex].id;
  if (Firebase.RTDB.setInt(&fbdo, "/tables/products/" + productId + "/product_quantity", newStock)) {
    Serial.println("DEBUG: Stock updated in Firebase for product " + String(productIndex));
  } else {
    Serial.println("DEBUG: Failed to update stock in Firebase: " + fbdo.errorReason());
  }
  
  // Always create a new transaction
  FirebaseJson json;
  json.set("product_name", products[productIndex].name);
  json.set("amount", products[productIndex].price);
  json.set("quantity", quantityDispensed);
  json.set("time", getTime());
  json.set("date", getDate());
  json.set("remaining", newStock);

  Serial.println("DEBUG: Attempting to add transaction to Firebase");
  if (Firebase.RTDB.pushJSON(&fbdo, "/tables/transactions", &json)) {
    Serial.println("DEBUG: Transaction successfully added to Firebase");
  } else {
    Serial.println("DEBUG: Failed to add transaction to Firebase: " + fbdo.errorReason());
    // If transaction fails, re-queue it
    String queuedTransaction = "TRANSACTION," + String(productIndex) + "," + String(quantityDispensed) + "," + String(newStock);
    transactionQueue.push(queuedTransaction);
    Serial.println("DEBUG: Transaction queued for retry: " + queuedTransaction);
  }
}

void handleRoot() {
  String html = "<html><body><h1>ESP32 Status</h1>";
  html += "<p>WiFi: " + String(isConnectedToWiFi ? "Connected" : "Disconnected") + "</p>";
  html += "<p>Firebase: " + String(isConnectedToFirebase ? "Connected" : "Disconnected") + "</p>";
  html += "<h2>Products:</h2>";
  for (int i = 0; i < 2; i++) {
    html += "<p>Product " + String(i+1) + ": " + products[i].name + ", Price: $" + String(products[i].price) + ", Quantity: " + String(products[i].quantity) + "</p>";
  }
  html += "</body></html>";
  server.send(200, "text/html", html);
}

void requestCurrentStock() {
  Serial.println("REQUEST_STOCK");
}

void handleCurrentStock(String data) {
  Serial.println("DEBUG: Received current stock from Arduino: " + data);
  int firstComma = data.indexOf(',');
  int secondComma = data.indexOf(',', firstComma + 1);
  
  int stock0 = data.substring(firstComma + 1, secondComma).toInt();
  int stock1 = data.substring(secondComma + 1).toInt();

  Serial.println("DEBUG: Parsed stock values - Product 0: " + String(stock0) + ", Product 1: " + String(stock1));

  // Update local product data
  products[0].quantity = stock0;
  products[1].quantity = stock1;

  // Update Firebase
  updateStocksInFirebase();
}

void updateStocksInFirebase() {
  Serial.println("Updating stocks in Firebase...");
  for (int i = 0; i < 2; i++) {
    if (Firebase.RTDB.setInt(&fbdo, "/tables/products/" + products[i].id + "/product_quantity", products[i].quantity)) {
      Serial.println("Stock updated in Firebase for product " + String(i) + ": " + String(products[i].quantity));
    } else {
      Serial.println("Failed to update stock in Firebase for product " + String(i) + ": " + fbdo.errorReason());
    }
  }
}

void handleResetData(String data) {
  Serial.println("Received reset data from Arduino");
  
  if (!checkConnection()) {
    Serial.println("No internet connection. Storing reset data for later.");
    resetPending = true;
    pendingResetData = data;
    return;
  }

  updateFirebaseWithResetData(data);
}

// Add this new function to update Firebase with reset data
void updateFirebaseWithResetData(String data) {
  // Parse and update Firebase
  String items[6];
  int index = 0;
  int startPos = data.indexOf(',') + 1;
  while (startPos > 0 && index < 6) {
    int endPos = data.indexOf(',', startPos);
    if (endPos == -1) {
      items[index] = data.substring(startPos);
    } else {
      items[index] = data.substring(startPos, endPos);
    }
    startPos = endPos + 1;
    index++;
  }

  for (int i = 0; i < 2; i++) {
    String productPath = "/tables/products/" + products[i].id;
    Firebase.RTDB.setString(&fbdo, productPath + "/product_name", items[i*3]);
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_price", items[i*3+1].toInt());
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_quantity", items[i*3+2].toInt());
    
    // Update local product data
    products[i].name = items[i*3];
    products[i].price = items[i*3+1].toInt();
    products[i].quantity = items[i*3+2].toInt();
  }

  Serial.println("Reset data updated in Firebase");
  resetPending = false;
  pendingResetData = "";
}

// Add these helper functions to get current time and date
String getTime() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) {
    Serial.println("Failed to obtain time");
    return "00:00:00";
  }
  char timeString[9];
  strftime(timeString, sizeof(timeString), "%H:%M:%S", &timeinfo);
  return String(timeString);
}

String getDate() {
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo)) {
    Serial.println("Failed to obtain date");
    return "1970-01-01";
  }
  char dateString[11];
  strftime(dateString, sizeof(dateString), "%Y-%m-%d", &timeinfo);
  return String(dateString);
}