#include <Stepper.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <ArduinoJson.h>
#include <EEPROM.h>

const int buttonPin6 = 6; // Pin connected to button 7 (B1)
const int buttonPin7 = 7; // Pin connected to button 8 (B2)
const int coinPin = 3; // Pin connected to the coin signal
const int coinRejector = 12; // Pin for coin rejector
const int buzzerPin = 8; // Pin connected to the buzzer
const int debounceTime = 50; // Debounce time in milliseconds
const int redLEDPin = 9; // Pin for red LED
const int greenLEDPin = 10; // Pin for green LED
const int blueLEDPin = 11; // Pin for blue LED

// Multi-dimensional array for products: {price, stock, name}
// 0 = Napkin, 1 = Wipes
const int product_count = 2;
String product_name[product_count] = {"Product 1", "Product 2"};
int product_price[product_count] = {1, 1};
int product_quantity[product_count] = {1, 1};

LiquidCrystal_I2C lcd(0x27, 16, 2); // Initialize the LCD with I2C address

// Variables for coin detection
unsigned long lastPulseTime = 0;

// Variable to hold the current credit
volatile bool insert = false;
int credit = 0;

// Variables for dispensing logic
bool dispensingNapkin = false;
bool dispensingWipes = false;

// Timer variables for page switching
unsigned long previousMillis = 0; // Store last time page was updated
const long interval = 2000; // Interval at which to switch pages
bool showFirstPage = true; // Flag to track which page is currently being shown

bool updateRequested = false;

// Define the Product struct
struct Product {
  String name;
  int price;
  int quantity;
};

// Define the products array
Product products[2];

bool updateInProgress = false;
String updateBuffer = "";

// Declare Stepper objects globally
Stepper napkinStepper(2048, 31, 35, 33, 37); // 2048 steps per revolution
Stepper wipesStepper(2048, 22, 26, 24, 28); // 2048 steps per revolution

// Add this function to send updates to ESP32
void sendStockUpdateToESP32(int productIndex, int newStock) {
  Serial.println("STOCK_UPDATE," + String(productIndex) + "," + String(newStock));
}

const int EEPROM_PARTITION1_START = 0;
const int EEPROM_PARTITION2_START = 512; // Adjust based on your EEPROM size

bool isFirstBoot() {
  // Check a specific address in EEPROM for a flag
  byte flag;
  EEPROM.get(EEPROM_PARTITION1_START - 1, flag);
  return flag != 0x42;  // 0x42 is an arbitrary value
}

void setFirstBootFlag() {
  // Set the flag to indicate it's no longer the first boot
  byte flag = 0x42;
  EEPROM.put(EEPROM_PARTITION1_START - 1, flag);
}

void setup() {
  Serial.begin(115200);  // Make sure this matches the baud rate used by the ESP32

  // Initialize hardware
  pinMode(buttonPin6, INPUT);
  pinMode(buttonPin7, INPUT);
  pinMode(coinPin, INPUT);
  pinMode(buzzerPin, OUTPUT);
  pinMode(coinRejector, OUTPUT);
  pinMode(redLEDPin, OUTPUT);
  pinMode(greenLEDPin, OUTPUT);
  pinMode(blueLEDPin, OUTPUT);
  pinMode(47, INPUT); // Set reset button pin as input (using external resistor)

  napkinStepper.setSpeed(5);
  wipesStepper.setSpeed(5);
  attachInterrupt(digitalPinToInterrupt(coinPin), coinInterrupt, FALLING);

  digitalWrite(coinRejector, LOW);
  digitalWrite(redLEDPin, HIGH);
  digitalWrite(greenLEDPin, LOW);
  digitalWrite(blueLEDPin, LOW);

  lcd.init();
  lcd.backlight();
  lcd.clear();

  if (isFirstBoot()) {
    // First boot, set empty values
    for (int i = 0; i < product_count; i++) {
      product_name[i] = "";
      product_price[i] = 0;
      product_quantity[i] = 0;
    }
    setFirstBootFlag();
    saveToPartition1();  // Save empty values
  } else {
    // Not first boot, load last saved data
    loadFromPartition1();
  }

  updateGlobalVariables();

  // Startup Screen
  for (int i = 0; i < 3; ++i) {
    lcd.clear();
    lcd.setCursor((16 - 12) / 2, 0);
    lcd.print("HygienexCare");
    delay(500);
    lcd.clear();
    lcd.setCursor((16 - 14) / 2, 1);
    lcd.print("VendingMachine");
    delay(500);
  }

  lcd.clear();
  lcd.setCursor((16 - 12) / 2, 0);
  lcd.print("HygienexCare");
  lcd.setCursor((16 - 14) / 2, 1);
  lcd.print("VendingMachine");
  delay(3000);

  // Display Default Screen
  lcd.clear();
  delay(300);

  // Display first page immediately
  showFirstPage = true;
  updateDisplay();

  // Initialize default values in EEPROM if not already set
  if (product_name[0] == "" || product_name[1] == "") {
    saveDefaultsToPartition2();
  }
}

void loop() {
  if (Serial.available() > 0) {
    String data = Serial.readStringUntil('\n');
    data.trim();  // Remove any whitespace

    if (data == "START_UPDATE") {
      updateInProgress = true;
      updateBuffer = "";
      lcd.clear();
      lcd.print("Updating...");
    } else if (updateInProgress && data.startsWith("U,")) {
      updateBuffer = data;
    } else if (data == "END_UPDATE") {
      updateInProgress = false;
      processUpdate(updateBuffer);
    }
  }

  unsigned long currentMillis = millis();
  if (currentMillis - previousMillis >= interval) {
    updateDisplay();
  }

  int button7State = digitalRead(buttonPin6);
  int button8State = digitalRead(buttonPin7);

  if (button7State == HIGH && !dispensingNapkin && !dispensingWipes) {
    handleProductSelection(0);
  } else if (button8State == HIGH && !dispensingNapkin && !dispensingWipes) {
    handleProductSelection(1);
  }

  delay(50);

  checkResetButton();
}

void processUpdate(String data) {
  bool anyUpdates = false;
  data = data.substring(2);  // Remove "U,"
  for (int i = 0; i < 2; i++) {
    int firstComma = data.indexOf(',');
    int secondComma = data.indexOf(',', firstComma + 1);
    int thirdComma = data.indexOf(',', secondComma + 1);
    
    String name = data.substring(0, firstComma);
    String priceStr = data.substring(firstComma + 1, secondComma);
    String quantityStr = data.substring(secondComma + 1, thirdComma);
    
    if (name != "NC" && priceStr != "NC" && quantityStr != "NC") {
      anyUpdates = true;
      product_name[i] = name;
      product_price[i] = priceStr.toFloat();
      product_quantity[i] = quantityStr.toInt();
      
      Serial.println("Updated product " + String(i) + ": " + name + ", " + priceStr + ", " + quantityStr);
    }
    
    if (thirdComma != -1) {
      data = data.substring(thirdComma + 1);
    } else {
      break;  // No more data to process
    }
  }
  
  if (anyUpdates) {
    lcd.clear();
    lcd.print("All products");
    lcd.setCursor(0, 1);
    lcd.print("updated!");
    delay(2000);  // Show the message for 2 seconds
    updateDisplay();  // Update the display with the new information
  } else {
    updateDisplay();  // Just refresh the display
  }
}

// Function to handle coin insertion logic
void handleCoinInsertion(int productPrice, int& stock, bool isNapkin) {
  lcd.clear();
  lcd.print("Insert ");
  lcd.print(productPrice); // Display the price of the selected product
  lcd.print(" Pesos");
  digitalWrite(coinRejector, HIGH);
  digitalWrite(redLEDPin, LOW);
  digitalWrite(greenLEDPin, HIGH);
  digitalWrite(blueLEDPin, LOW);

  unsigned long insertionStartTime = millis(); // Start timer for coin insertion
  int animationState = 0;
  unsigned long lastAnimationUpdate = millis();

  while (credit < productPrice) { // Wait until the required coins are inserted
    if (insert) {
      insert = false; // Reset insert flag
      credit++; // Increment credit for each coin inserted
      lcd.clear(); // Clear the display
      lcd.print("Credits: ");
      lcd.print(credit, DEC); // Print updated credit value
      lcd.print(" Pesos"); // Show currency
      lcd.setCursor(0, 1);
      lcd.print("Inserting");
      Serial.print("Coins inserted: ");
      Serial.println(credit); // Debugging output
    }

    // Animation logic
    if (millis() - lastAnimationUpdate > 250) { // Update animation every 250ms
      lastAnimationUpdate = millis();
      lcd.setCursor(9, 1); // Position cursor for animation
      switch (animationState) {
        case 0:
          lcd.print(" ");
          break;
        case 1:
          lcd.print(". ");
          break;
        case 2:
          lcd.print(".. ");
          break;
        case 3:
          lcd.print("...");
          break;
      }
      animationState = (animationState + 1) % 4; // Cycle through animation states
    }

    // Check for timeout
    if (millis() - insertionStartTime >= 100000) { // 100000 milliseconds = 100 seconds
      lcd.clear();
      lcd.print("TIMEOUT!"); // Show timeout message
      digitalWrite(coinRejector, LOW);
      delay(2000); // Pause to display timeout message
      goToIdleMessage(); // Go back to idle screen
      return; // Exit the function
    }
  }

  lcd.clear();
  lcd.print("PROCESSING...");
  digitalWrite(coinRejector, LOW);
  digitalWrite(redLEDPin, LOW);
  digitalWrite(greenLEDPin, LOW);
  digitalWrite(blueLEDPin, HIGH);
  delay(2000); // Simulate dispensing process

  lcd.clear();
  delay(100); // Simulate dispensing process
  lcd.print(product_name[isNapkin ? 0 : 1]);
  lcd.setCursor(0, 1);
  lcd.print("DISPENSING ...");

  // Dispensing process
  if (isNapkin) {
    dispensingNapkin = true;
    napkinStepper.step(1024); // Rotate from 0 to 180 degrees (1024 steps)
    napkinStepper.step(-1024); // Rotate back to 0 degrees
    product_quantity[0]--; // Decrement napkin stock once
    sendStockUpdateToESP32(0, product_quantity[0]); // Send update for product 0
    saveToPartition1(); // Save updated quantity to EEPROM
  } else {
    dispensingWipes = true;
    wipesStepper.step(1024); // Rotate from 0 to 180 degrees (1024 steps)
    wipesStepper.step(-1024); // Rotate back to 0 degrees
    product_quantity[1]--; // Decrement wipes stock once
    sendStockUpdateToESP32(1, product_quantity[1]); // Send update for product 1
    saveToPartition1(); // Save updated quantity to EEPROM
  }

  delay(100); // Simulate dispensing process

  lcd.clear();
  lcd.print("SUCCESSFULLY");
  lcd.setCursor(0, 1);
  lcd.print("DISPENSED!");
  delay(2000); // Simulate dispensing process

  lcd.clear();
  lcd.print("THANK YOU!");
  digitalWrite(blueLEDPin, LOW);
  digitalWrite(greenLEDPin, LOW);
  delay(2000); // Simulate dispensing process

  // Reset dispensing flags
  dispensingNapkin = false;
  dispensingWipes = false;
  credit = 0;
  insert = false;

  goToIdleMessage(); // Go back to idle screen
}

// Function to display product options
void displayProductOptions() {
  lcd.clear();
  lcd.print("Select Product:");
  lcd.setCursor(0, 1);
  lcd.print("1:" + product_name[0].substring(0, 7) + " 2:" + product_name[1].substring(0, 7));
}

// Function to go back to the default idle message
void goToIdleMessage() {

  lcd.clear();
  delay(100); // Simulate a brief pause before showing idle screen
  previousMillis = millis(); // Reset the timing for page switching
  showFirstPage = true; // Reset to show the first page initially
  digitalWrite(redLEDPin, HIGH);
  digitalWrite(greenLEDPin, LOW);
  digitalWrite(blueLEDPin, LOW);

}

// Function to buzz warning sound
void buzzWarning() {
  digitalWrite(buzzerPin, HIGH);
  delay(500);
  digitalWrite(buzzerPin, LOW);
}

void coinInterrupt() {
  if (millis() - lastPulseTime > debounceTime) { // Check for debounce
    insert = true; // Set insert flag
    lastPulseTime = millis(); // Update last pulse time
  }
}

void updateDisplay() {
  previousMillis = millis();

  lcd.clear();
  if (showFirstPage) {
    lcd.setCursor((16 - 12) / 2, 0);
    lcd.print("HygienexCare");
    lcd.setCursor((16 - 16) / 2, 1);
    lcd.print("SELECT A PRODUCT");
  } else {
    lcd.setCursor(0, 0);
    lcd.print("1=" + (product_name[0].length() > 0 ? product_name[0] : "Empty"));
    lcd.setCursor(0, 1);
    lcd.print("2=" + (product_name[1].length() > 0 ? product_name[1] : "Empty"));
    
    // Display prices if there's enough space
    if (product_name[0].length() <= 8) {
      lcd.setCursor(10, 0);
      lcd.print("P" + String(product_price[0]));
    }
    if (product_name[1].length() <= 8) {
      lcd.setCursor(10, 1);
      lcd.print("P" + String(product_price[1]));
    }
  }

  showFirstPage = !showFirstPage;
}

void handleProductSelection(int productIndex) {
  if (product_quantity[productIndex] > 0) {
    credit = 0; // Reset credit for new transaction
    lcd.clear();
    lcd.print(product_name[productIndex]);
    lcd.setCursor(0, 1);
    lcd.print("Stock: ");
    lcd.print(product_quantity[productIndex]);

    delay(2000); // Pause to show stock info

    if (confirmProceed("Want to Proceed?")) {
      handleCoinInsertion(product_price[productIndex], product_quantity[productIndex], productIndex == 0);
    } else {
      goToIdleMessage();
    }
  } else {
    lcd.clear();
    lcd.print(product_name[productIndex]);
    lcd.setCursor(0, 1);
    lcd.print("OUT OF STOCK!");
    digitalWrite(coinRejector, LOW);
    digitalWrite(greenLEDPin, LOW);
    digitalWrite(blueLEDPin, LOW);

    // Blink the red LED and buzz synchronously
    for (int i = 0; i < 5; i++) { // Blink and buzz 5 times
      digitalWrite(redLEDPin, HIGH);
      digitalWrite(buzzerPin, HIGH);
      delay(250);
      digitalWrite(redLEDPin, LOW);
      digitalWrite(buzzerPin, LOW);
      delay(250);
    }

    delay(2000);
    goToIdleMessage();
  }
}

bool confirmProceed(const char* message) {
  lcd.clear();
  lcd.print(message);
  lcd.setCursor(0, 1);
  lcd.print("1 = Yes 2 = No");

  unsigned long startTime = millis();
  while (millis() - startTime < 5000) { // 5-second timeout
    if (digitalRead(buttonPin6) == HIGH) {
      delay(50); // Debounce
      return true; // User chose Yes
    }
    if (digitalRead(buttonPin7) == HIGH) {
      delay(50); // Debounce
      return false; // User chose No
    }
  }
  return false; // Timeout, treat as No
}

// Function to update product data
bool updateProductData() {
  if (Serial.available()) {
    String jsonString = Serial.readStringUntil('\n');
    StaticJsonDocument<512> doc;
    DeserializationError error = deserializeJson(doc, jsonString);

    if (error) {
      lcd.clear();
      lcd.print("Update failed");
      lcd.setCursor(0, 1);
      lcd.print("Invalid Data");
      delay(2000);
      return false;
    }

    JsonArray products = doc["products"];
    bool changed = false;
    for (int i = 0; i < product_count && i < products.size(); i++) {
      JsonObject product = products[i];
      String newName = product["name"].as<String>();
      int newPrice = product["price"];
      int newQuantity = product["stocks"];

      if (product_name[i] != newName || product_price[i] != newPrice || product_quantity[i] != newQuantity) {
        product_name[i] = newName;
        product_price[i] = newPrice;
        product_quantity[i] = newQuantity;
        changed = true;
      }
    }

    if (changed) {
      lcd.clear();
      lcd.print("Update Complete!");
      lcd.setCursor(0, 1);
      lcd.print("All Data Updated");
      delay(2000);
      saveToPartition1(); // Save updated data to EEPROM
    } else {
      lcd.clear();
      lcd.print("No changes");
      lcd.setCursor(0, 1);
      lcd.print("detected");
      delay(2000);
    }

    return changed;
  }
  return false;
}

void saveToPartition1() {
  int addr = EEPROM_PARTITION1_START;
  for (int i = 0; i < product_count; i++) {
    EEPROM.put(addr, product_name[i]);
    addr += sizeof(product_name[i]);
    EEPROM.put(addr, product_price[i]);
    addr += sizeof(product_price[i]);
    EEPROM.put(addr, product_quantity[i]);
    addr += sizeof(product_quantity[i]);
  }
}

void loadFromPartition1() {
  int addr = EEPROM_PARTITION1_START;
  for (int i = 0; i < product_count; i++) {
    EEPROM.get(addr, product_name[i]);
    addr += sizeof(product_name[i]);
    EEPROM.get(addr, product_price[i]);
    addr += sizeof(product_price[i]);
    EEPROM.get(addr, product_quantity[i]);
    addr += sizeof(product_quantity[i]);
  }
}

void saveDefaultsToPartition2() {
  int addr = EEPROM_PARTITION2_START;
  String defaultNames[2] = {"Product 1", "Product 2"};
  int defaultPrices[2] = {1, 1};
  int defaultQuantities[2] = {1, 1};
  
  for (int i = 0; i < product_count; i++) {
    EEPROM.put(addr, defaultNames[i]);
    addr += sizeof(defaultNames[i]);
    EEPROM.put(addr, defaultPrices[i]);
    addr += sizeof(defaultPrices[i]);
    EEPROM.put(addr, defaultQuantities[i]);
    addr += sizeof(defaultQuantities[i]);
  }
}

void loadFromPartition2() {
  int addr = EEPROM_PARTITION2_START;
  for (int i = 0; i < product_count; i++) {
    EEPROM.get(addr, product_name[i]);
    addr += sizeof(product_name[i]);
    EEPROM.get(addr, product_price[i]);
    addr += sizeof(product_price[i]);
    EEPROM.get(addr, product_quantity[i]);
    addr += sizeof(product_quantity[i]);
  }
}

void resetToDefault() {
  String defaultNames[2] = {"Product 1", "Product 2"};
  int defaultPrices[2] = {1, 1};
  int defaultQuantities[2] = {1, 1};
  
  for (int i = 0; i < product_count; i++) {
    product_name[i] = defaultNames[i];
    product_price[i] = defaultPrices[i];
    product_quantity[i] = defaultQuantities[i];
  }
  
  saveToPartition1(); // Save these default values to Partition 1
  saveDefaultsToPartition2(); // Also save to Partition 2 for future resets
  updateGlobalVariables();
}

void updateGlobalVariables() {
  for (int i = 0; i < product_count; i++) {
    products[i].name = product_name[i];
    products[i].price = product_price[i];
    products[i].quantity = product_quantity[i];
  }
}

void checkResetButton() {
  const int resetButtonPin = 47;
  static bool lastResetState = HIGH;
  bool currentResetState = digitalRead(resetButtonPin);

  if (lastResetState == LOW && currentResetState == HIGH) {
    delay(50); // Debounce
    resetToDefault();
    updateGlobalVariables();
    lcd.clear();
    lcd.print("Reset to default");
    delay(2000);
    goToIdleMessage(); // Go to idle message after resetting
  }

  lastResetState = currentResetState;
}