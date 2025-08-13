importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');

firebase.initializeApp({
    apiKey: "AIzaSyDnPPwp67SJExYPX-ZDFtbZAFhm64CJxuo",
    authDomain: "ozi-technologies-99593.firebaseapp.com",
    projectId: "ozi-technologies-99593",
    storageBucket: "ozi-technologies-99593.firebasestorage.app",
    messagingSenderId: "436594923301",
    appId: "1:436594923301:android:e105e4164ed9fa03962ccc",
    measurementId: "G-4NSPLT0604"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function (payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body ? payload.data.body : '',
        icon: payload.data.icon ? payload.data.icon : ''
    });
});