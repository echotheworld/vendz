#include <WiFi.h>
//#include <WebServer.h>
#include <DNSServer.h>
#include <WiFiManager.h>
#include <Firebase_ESP_Client.h>
#include <addons/TokenHelper.h>
#include <addons/RTDBHelper.h>
#include <time.h>

#define API_KEY "AIzaSyD_5JkJaZr60O2FZ80H84HL9u6lAjgrZWI"
#define DATABASE_URL "https://dbvending-1b336-default-rtdb.firebaseio.com"
#define MAX_OFFLINE_TRANSACTIONS 50
#define WIFI_RESET_PIN 0

FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;

WiFiManager wifiManager;
//WebServer server(80);

unsigned long lastUpdateTime = 0;
const unsigned long updateInterval = 5000; // Check for updates every 5 seconds

unsigned long lastPresenceUpdateTime = 0;
const unsigned long presenceUpdateInterval = 3000; // Update presence every 3 seconds
int presenceCounter = 0;

struct Product {
  String id;
  String identity;  // Add this line
  String name;
  float price;
  int quantity;
  bool changed;
};

Product products[2] = {
  {"-O8GSb1L_mn9CLR1Z0Ly", "Product1", "", 0, 0, false},
  {"-O8GSldDvFUT5olxHCiX", "Product2", "", 0, 0, false}
};

bool isConnectedToWiFi = false;
bool isConnectedToFirebase = false;

bool wasDisconnected = false;

// Add these global variables at the top of your file
bool resetPending = false;
String pendingResetData = "";



struct OfflineTransaction {
    int productIndex;
    float amount;
    char date[11];
    char time[9];
    int remaining;
    char productIdentity[20];  // Add this line, adjust size as needed
};

OfflineTransaction offlineTransactions[MAX_OFFLINE_TRANSACTIONS];
int offlineTransactionCount = 0;

// Add this function to store offline transactions
void storeOfflineTransaction(int productIndex, int newStock) {
    if (offlineTransactionCount < MAX_OFFLINE_TRANSACTIONS) {
        OfflineTransaction* transaction = &offlineTransactions[offlineTransactionCount];
        transaction->productIndex = productIndex;
        transaction->amount = products[productIndex].price;
        transaction->remaining = newStock;
        strncpy(transaction->productIdentity, products[productIndex].identity.c_str(), sizeof(transaction->productIdentity) - 1);
        transaction->productIdentity[sizeof(transaction->productIdentity) - 1] = '\0';  // Ensure null-termination

        struct tm timeinfo;
        if (getLocalTime(&timeinfo)) {
            strftime(transaction->date, sizeof(transaction->date), "%Y-%m-%d", &timeinfo);
            strftime(transaction->time, sizeof(transaction->time), "%H:%M:%S", &timeinfo);
        } else {
            strcpy(transaction->date, "0000-00-00");
            strcpy(transaction->time, "00:00:00");
        }

        offlineTransactionCount++;
        Serial.println("Stored offline transaction. Total: " + String(offlineTransactionCount));
    } else {
        Serial.println("Offline transaction storage full. Cannot store more.");
    }
}

// Function prototypes
bool updateProductData();
void sendUpdateToArduino();
void updateStockInFirebase(String data);
void updateESP32Presence();
void handleRoot();
void clearAllTransactions();
void removeAllUsersExceptOne();
void updateUserDataOnReset();


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

void setupWiFi() {
  WiFi.mode(WIFI_STA); // Set WiFi to station mode
  WiFi.begin(); // Try to connect using saved credentials

  Serial.println("Connecting to WiFi...");
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) { // Try for about 10 seconds
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nConnected to WiFi");
    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());
    isConnectedToWiFi = true;
  } else {
    Serial.println("\nFailed to connect to WiFi. Please use the reset button to enter AP mode.");
    isConnectedToWiFi = false;
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(WIFI_RESET_PIN, INPUT_PULLUP);
  delay(1000);
  Serial.println("ESP32 Starting Up");
  Serial.println("//");

  setupWiFi();

  if (!isConnectedToWiFi) {
    return;
  }

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

  Serial.print("Updating the Product ...");
  if (updateProductData()) {
    Serial.println("Product Updated!");
  } else {
    Serial.println("\nNo product updates needed");
  }
  Serial.println("//");

  //server.on("/", handleRoot);
  //server.begin();
  //Serial.println("HTTP server started");

  Serial.println("Then Start Counting ...");

  configTime(8 * 3600, 0, "pool.ntp.org", "time.nist.gov");
  
  // Wait for time to be set
  int retry = 0;
  struct tm timeinfo;
  while (!getLocalTime(&timeinfo) && retry < 10) {
    Serial.println("Failed to obtain time, retrying...");
    delay(1000);
    retry++;
  }
  
  if (retry == 10) {
    Serial.println("Failed to obtain time after 10 retries. Continuing without accurate time.");
  } else {
    Serial.println("Time obtained successfully");
  }
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi connection lost. Attempting to reconnect...");
    setupWiFi();
    if (!isConnectedToWiFi) {
      delay(10000); // Wait 10 seconds before trying again
      return;
    }
  }

  unsigned long currentMillis = millis();
  
  if (currentMillis - lastPresenceUpdateTime > presenceUpdateInterval) {
    lastPresenceUpdateTime = currentMillis;
    updateESP32Presence();
  }

  bool connectionStatus = checkConnection();
  
  if (connectionStatus) {
    if (wasDisconnected) {
      // Connection regained, request current stock first
      requestCurrentStock();
      processOfflineTransactions();  // Add this line
      wasDisconnected = false;
      lastUpdateTime = currentMillis; // Reset the update timer
    }
    
    if (resetPending) {
      performReset();
    }
  } else {
    wasDisconnected = true;
  }

  if (Serial.available() > 0) {
    String data = Serial.readStringUntil('\n');
    if (data.startsWith("STOCK_UPDATE")) {
      updateStockInFirebase(data);
    } else if (data.startsWith("CURRENT_STOCK")) {
      handleCurrentStock(data);
      lastUpdateTime = currentMillis;
    } else if (data.startsWith("RESET_DATA")) {
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

  checkWiFiResetButton();
  //server.handleClient();
  delay(10);
}

bool updateProductData() {
  bool anyChanges = false;
  for (int i = 0; i < 2; i++) {
    products[i].changed = false;  // Reset change flag
    if (Firebase.RTDB.getString(&fbdo, "/tables/products/" + products[i].id + "/product_identity")) {
      String newIdentity = fbdo.stringData();
      if (newIdentity != products[i].identity) {
        products[i].identity = newIdentity;
        products[i].changed = true;
        anyChanges = true;
      }
    }
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
      updateString += products[i].identity + "," + products[i].name + "," + String(products[i].price, 2) + "," + String(products[i].quantity);
    } else {
      updateString += "NC,NC,NC,NC";
    }
    if (i == 0) updateString += ",";
  }
  Serial.println(updateString);
}

void updateStockInFirebase(String data) {
    Serial.println("Updating stock in Firebase. Received data: " + data);

    int firstComma = data.indexOf(',');
    int secondComma = data.indexOf(',', firstComma + 1);
    int thirdComma = data.indexOf(',', secondComma + 1);
    
    String productIdentity = data.substring(firstComma + 1, secondComma);
    int productIndex = data.substring(secondComma + 1, thirdComma).toInt();
    int newStock = data.substring(thirdComma + 1).toInt();

    Serial.println("Product Identity: " + productIdentity + ", Product Index: " + String(productIndex) + ", New Stock: " + String(newStock));

    // Update the local product data
    products[productIndex].quantity = newStock;
    products[productIndex].identity = productIdentity;  // Add this line

    // Update Firebase
    String productId = products[productIndex].id;
    String updatePath = "/tables/products/" + productId + "/product_quantity";
    Serial.println("Updating stock at path: " + updatePath);

    if (!checkConnection()) {
        Serial.println("No internet connection. Storing stock update for later.");
        storeOfflineTransaction(productIndex, newStock);
        return;
    }

    if (Firebase.RTDB.setInt(&fbdo, updatePath.c_str(), newStock)) {
        Serial.println("Stock updated in Firebase for product " + productIdentity);
        createTransaction(productIndex, newStock);
    } else {
        Serial.println("Failed to update stock in Firebase: " + fbdo.errorReason());
    }
}

// void handleRoot() {
//   String html = "<html><body><h1>ESP32 Status</h1>";
//   html += "<p>WiFi: " + String(isConnectedToWiFi ? "Connected" : "Disconnected") + "</p>";
//   html += "<p>Firebase: " + String(isConnectedToFirebase ? "Connected" : "Disconnected") + "</p>";
//   html += "<h2>Products:</h2>";
//   for (int i = 0; i < 2; i++) {
//     html += "<p>Product " + String(i+1) + ": " + products[i].name + ", Price: $" + String(products[i].price) + ", Quantity: " + String(products[i].quantity) + "</p>";
//   }
//   html += "</body></html>";
//   server.send(200, "text/html", html);
// }

void requestCurrentStock() {
  Serial.println("REQUEST_STOCK");
}

void handleCurrentStock(String data) {
  Serial.println("Received current stock from Arduino: " + data);
  int firstComma = data.indexOf(',');
  int secondComma = data.indexOf(',', firstComma + 1);
  int thirdComma = data.indexOf(',', secondComma + 1);
  int fourthComma = data.indexOf(',', thirdComma + 1);
  
  String identity0 = data.substring(firstComma + 1, secondComma);
  int stock0 = data.substring(secondComma + 1, thirdComma).toInt();
  String identity1 = data.substring(thirdComma + 1, fourthComma);
  int stock1 = data.substring(fourthComma + 1).toInt();

  // Update local product data
  products[0].identity = identity0;
  products[0].quantity = stock0;
  products[1].identity = identity1;
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
  if (!checkConnection()) {
    Serial.println("No internet connection. Storing reset data for later.");
    resetPending = true;
    pendingResetData = data;
    return;
  }
  performReset();
}

void performReset() {
  Serial.println("Performing reset...");
  
  // 1. Empty the products
  for (int i = 0; i < 2; i++) {
    String productPath = "/tables/products/" + products[i].id;
    String productName = "Prod" + String(i + 1) + " Empty";
    Firebase.RTDB.setString(&fbdo, productPath + "/product_name", productName);
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_price", 0);
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_quantity", 0);
    
    // Update local product data
    products[i].name = productName;
    products[i].price = 0;
    products[i].quantity = 0;
  }

  // 2. Clear all transactions
  Firebase.RTDB.deleteNode(&fbdo, "tables/transactions");

  // 3. Clear activity logs
  Firebase.RTDB.deleteNode(&fbdo, "activity_logs");

  // 4. Clear all users (including the main admin)
  Firebase.RTDB.deleteNode(&fbdo, "tables/user");

  // 5. Recreate the main admin user
  updateUserDataOnReset();

  Serial.println("Reset completed.");
  resetPending = false;
  pendingResetData = "";
}

void clearAllTransactions() {
  Serial.println("Clearing all transactions...");
  if (Firebase.RTDB.deleteNode(&fbdo, "tables/transactions")) {
    Serial.println("All transactions cleared successfully");
  } else {
    Serial.println("Failed to clear transactions: " + fbdo.errorReason());
  }
}

void removeAllUsersExceptOne() {
  Serial.println("Removing all users except the main admin...");
  if (Firebase.RTDB.getJSON(&fbdo, "tables/user")) {
    FirebaseJson *json = fbdo.to<FirebaseJson *>();
    FirebaseJsonData result;
    size_t count = json->iteratorBegin();
    String mainAdminKey = "-O8FwN7EsoD-lRKW8z8z";  // Main admin user key

    for (size_t i = 0; i < count; i++) {
      String key;
      int type = 0;
      String value;
      json->iteratorGet(i, type, key, value);
      if (type == FirebaseJson::JSON_OBJECT && key != mainAdminKey) {
        if (Firebase.RTDB.deleteNode(&fbdo, "tables/user/" + key)) {
          Serial.println("Removed user: " + key);
        } else {
          Serial.println("Failed to remove user " + key + ": " + fbdo.errorReason());
        }
      }
    }

    json->iteratorEnd();
    Serial.println("User removal completed. Only main admin remains.");
  } else {
    Serial.println("Failed to fetch users: " + fbdo.errorReason());
  }
}

void updateUserDataOnReset() {
  Serial.println("Updating user data on reset...");
  String userPath = "tables/user/-O8FwN7EsoD-lRKW8z8z";
  FirebaseJson json;
  json.set("first_login", true);
  json.set("u_role", "admin");
  json.set("user_contact", "");
  json.set("user_email", "");
  json.set("user_id", "admin");
  json.set("user_pass", "@4dmin_HC!");

  Serial.println("Attempting to update user data in Firebase...");
  if (Firebase.RTDB.setJSON(&fbdo, userPath.c_str(), &json)) {
    Serial.println("User data updated successfully in Firebase");
    Serial.print("Updated data: ");
    Serial.println(json.raw());
  } else {
    Serial.println("Failed to update user data in Firebase");
    Serial.println("Error reason: " + fbdo.errorReason());
  }
}

// Add this new function to update Firebase with reset data
void updateFirebaseWithResetData(String data) {
  // Parse and update Firebase
  String items[8];
  int index = 0;
  int startPos = data.indexOf(',') + 1;
  while (startPos > 0 && index < 8) {
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
    Firebase.RTDB.setString(&fbdo, productPath + "/product_identity", items[i*4]);
    Firebase.RTDB.setString(&fbdo, productPath + "/product_name", items[i*4+1]);
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_price", items[i*4+2].toInt());
    Firebase.RTDB.setInt(&fbdo, productPath + "/product_quantity", items[i*4+3].toInt());
    
    // Update local product data
    products[i].identity = items[i*4];
    products[i].name = items[i*4+1];
    products[i].price = items[i*4+2].toInt();
    products[i].quantity = items[i*4+3].toInt();
  }

  Serial.println("Reset data updated in Firebase");
  resetPending = false;
  pendingResetData = "";
  
  Serial.println("Now updating user data...");
  updateUserDataOnReset();
}

void createTransaction(int productIndex, int newStock) {
    if (!checkConnection()) {
        storeOfflineTransaction(productIndex, newStock);
        return;
    }

    Serial.println("Creating transaction for product index: " + String(productIndex));

    OfflineTransaction transaction;
    transaction.productIndex = productIndex;
    transaction.amount = products[productIndex].price;
    transaction.remaining = newStock;

    struct tm timeinfo;
    if (getLocalTime(&timeinfo)) {
        strftime(transaction.date, sizeof(transaction.date), "%Y-%m-%d", &timeinfo);
        strftime(transaction.time, sizeof(transaction.time), "%H:%M:%S", &timeinfo);
    } else {
        strcpy(transaction.date, "0000-00-00");
        strcpy(transaction.time, "00:00:00");
    }

    String transactionPath = "/tables/transactions/" + String(random(0xffff), HEX) + String(random(0xffff), HEX);
    Serial.println("Transaction path: " + transactionPath);

    FirebaseJson json;
    json.set("amount", transaction.amount);
    json.set("date", transaction.date);
    json.set("product_identity", products[productIndex].identity);  // Add this line
    json.set("product_name", products[productIndex].name);
    json.set("remaining", transaction.remaining);
    json.set("time", transaction.time);

    Serial.println("Attempting to create transaction in Firebase...");
    if (Firebase.RTDB.setJSON(&fbdo, transactionPath.c_str(), &json)) {
        Serial.println("Transaction created successfully");
        Serial.print("Transaction data: ");
        Serial.println(json.raw());
    } else {
        Serial.println("Failed to create transaction: " + fbdo.errorReason());
        storeOfflineTransaction(productIndex, newStock);
    }
}

// Add this function to process offline transactions
void processOfflineTransactions() {
    if (offlineTransactionCount == 0) return;

    Serial.println("Processing " + String(offlineTransactionCount) + " offline transactions");
    for (int i = 0; i < offlineTransactionCount; i++) {
        OfflineTransaction* transaction = &offlineTransactions[i];
        
        String transactionPath = "/tables/transactions/" + String(random(0xffff), HEX) + String(random(0xffff), HEX);
        
        FirebaseJson json;
        json.set("amount", transaction->amount);
        json.set("date", transaction->date);
        json.set("product_identity", transaction->productIdentity);  // Add this line
        json.set("product_name", products[transaction->productIndex].name);
        json.set("remaining", transaction->remaining);
        json.set("time", transaction->time);

        if (Firebase.RTDB.setJSON(&fbdo, transactionPath.c_str(), &json)) {
            Serial.println("Offline transaction uploaded successfully");
        } else {
            Serial.println("Failed to upload offline transaction: " + fbdo.errorReason());
            // If it fails, we'll keep it in the offline transactions
            continue;
        }
    }
    offlineTransactionCount = 0;
}

void checkWiFiResetButton() {
  if (digitalRead(WIFI_RESET_PIN) == LOW) {
    delay(50); // Debounce
    if (digitalRead(WIFI_RESET_PIN) == LOW) {
      Serial.println("WiFi Reset button pressed. Starting AP mode...");
      WiFiManager wifiManager;
      wifiManager.resetSettings();
      wifiManager.setConfigPortalTimeout(180); // Set timeout to 3 minutes
      if (!wifiManager.startConfigPortal("HygienexCare_AP", "password")) {
        Serial.println("Failed to connect and hit timeout");
      } else {
        Serial.println("Connected to new WiFi");
      }
      ESP.restart();
    }
  }
}



