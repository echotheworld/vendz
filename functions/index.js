/**
 * Import function triggers from their respective submodules:
 *
 * const {onCall} = require("firebase-functions/v2/https");
 * const {onDocumentWritten} = require("firebase-functions/v2/firestore");
 *
 * See a full list of supported triggers at https://firebase.google.com/docs/functions
 */

const functions = require("firebase-functions");
const admin = require("firebase-admin");
admin.initializeApp();

exports.createTransaction = functions.database
    .ref("/tables/products/{productId}/product_quantity")
    .onUpdate((change, context) => {
      const productId = context.params.productId;
      const newValue = change.after.val();
      const previousValue = change.before.val();

      // Only create a transaction if the quantity has decreased
      if (newValue < previousValue) {
        const quantityDispensed = previousValue - newValue;

        return admin.database().ref(`/tables/products/${productId}`)
            .once("value")
            .then((snapshot) => {
              const product = snapshot.val();
              const newTransaction = {
                product_name: product.product_name,
                amount: product.product_price,
                quantity: quantityDispensed,
                time: new Date().toLocaleTimeString(),
                date: new Date().toLocaleDateString(),
                remaining: newValue,
              };

              return admin.database()
                  .ref("/tables/transactions")
                  .push(newTransaction);
            });
      }
      return null;
    });
