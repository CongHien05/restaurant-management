package com.restaurant.staff.data.remote

import okhttp3.Interceptor
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import com.google.gson.GsonBuilder
import com.restaurant.staff.data.adapters.BooleanTypeAdapter
import java.util.concurrent.TimeUnit

object ApiClient {

//    private const val BASE_URL = "http://192.168.1.4:8081/pandabackend/api/"
//    private const val BASE_URL = "http://192.168.1.2:8081/pandabackend/api/"
    private const val BASE_URL = "http://192.168.1.7:8081/pandabackend/api/"
    private var authToken: String? = null

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = HttpLoggingInterceptor.Level.BODY
    }

    private val tokenExpirationInterceptor = Interceptor { chain ->
        val originalRequest = chain.request()

        // Check if token is expired before making the request
        if (isTokenExpired()) {
            // Token is expired, clear it and return error response
            clearAuthToken()
            // You can also trigger logout here if needed
            android.util.Log.w("ApiClient", "Token expired, cleared automatically")
        }

        chain.proceed(originalRequest)
    }

    private val unauthorizedResponseInterceptor = Interceptor { chain ->
        val response = chain.proceed(chain.request())

        // Check if response is 401 Unauthorized
        if (response.code == 401) {
            android.util.Log.w("ApiClient", "Received 401 Unauthorized, clearing token")
            clearAuthToken()
            // Note: We can't trigger logout here directly as we don't have context
            // The calling code should handle this by checking isLoggedIn()
        }

        response
    }

    private val authInterceptor = Interceptor { chain ->
        val originalRequest = chain.request()
        val requestBuilder = originalRequest.newBuilder()

        // Add auth token if available and not expired
        if (!isTokenExpired()) {
            authToken?.let { token ->
                requestBuilder.addHeader("Authorization", "Bearer $token")
            }
        }

        // Add common headers
        requestBuilder
            .addHeader("Content-Type", "application/json")
            .addHeader("Accept", "application/json")

        val request = requestBuilder.build()
        chain.proceed(request)
    }

    private val okHttpClient = OkHttpClient.Builder()
        .addInterceptor(tokenExpirationInterceptor)
        .addInterceptor(unauthorizedResponseInterceptor)
        .addInterceptor(authInterceptor)
        .addInterceptor(loggingInterceptor)
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .build()

    private val gson = GsonBuilder()
        .registerTypeAdapter(Boolean::class.java, BooleanTypeAdapter())
        .registerTypeAdapter(Boolean::class.javaPrimitiveType, BooleanTypeAdapter())
        .setLenient()
        .create()

    private val retrofit = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .client(okHttpClient)
        .addConverterFactory(GsonConverterFactory.create(gson))
        .build()

    // API Services
    val authService: AuthApiService by lazy { retrofit.create(AuthApiService::class.java) }
    val tableService: TableApiService by lazy { retrofit.create(TableApiService::class.java) }
    val menuService: MenuApiService by lazy { retrofit.create(MenuApiService::class.java) }
    val orderService: OrderApiService by lazy { retrofit.create(OrderApiService::class.java) }
    val adminService: AdminApiService by lazy { retrofit.create(AdminApiService::class.java) }

    fun setAuthToken(token: String?) {
        this.authToken = token
    }

    fun clearAuthToken() {
        this.authToken = null
    }

    fun isTokenExpired(): Boolean {
        if (authToken.isNullOrEmpty()) return true

        try {
            // Decode JWT token to check expiration
            val parts = authToken!!.split(".")
            if (parts.size != 3) return true

            val payload = parts[1]
            // Add padding if needed
            val paddedPayload = payload + "=".repeat((4 - payload.length % 4) % 4)
            val decodedBytes = android.util.Base64.decode(paddedPayload, android.util.Base64.URL_SAFE)
            val decodedString = String(decodedBytes)

            // Parse JSON to get expiration time
            val jsonObject = org.json.JSONObject(decodedString)
            val exp = jsonObject.optLong("exp", 0)

            // Check if token is expired (current time > expiration time)
            val currentTime = System.currentTimeMillis() / 1000
            return currentTime > exp

        } catch (e: Exception) {
            // If we can't decode the token, assume it's expired
            return true
        }
    }
}