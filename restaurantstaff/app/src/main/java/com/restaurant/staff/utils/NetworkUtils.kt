package com.restaurant.staff.utils

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import com.restaurant.staff.data.model.ApiResponse
import com.restaurant.staff.data.model.NetworkResult
import com.google.gson.GsonBuilder
import com.restaurant.staff.data.adapters.BooleanTypeAdapter


import retrofit2.HttpException
import retrofit2.Response
import java.io.IOException
import java.net.SocketTimeoutException

object NetworkUtils {

    fun isNetworkAvailable(context: Context): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE) as ConnectivityManager

        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network) ?: return false
            capabilities.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET)
        } else {
            @Suppress("DEPRECATION")
            val networkInfo = connectivityManager.activeNetworkInfo
            networkInfo?.isConnected == true
        }
    }

    suspend fun <T> safeApiCall(apiCall: suspend () -> Response<ApiResponse<T>>): NetworkResult<T> {
        return try {
            val response = apiCall()

            if (response.isSuccessful) {
                val body = response.body()
                if (body != null && body.success) {
                    NetworkResult.Success(body.data!!)
                } else {
                    NetworkResult.Error(
                        message = body?.message ?: "Unknown error occurred",
                        code = response.code()
                    )
                }
            } else {
                val errorBody = response.errorBody()?.string()
                NetworkResult.Error(
                    message = parseErrorMessage(errorBody) ?: "Request failed",
                    code = response.code()
                )
            }
        } catch (e: Exception) {
            NetworkResult.Error(
                message = when (e) {
                    is SocketTimeoutException -> "Request timeout. Please check your connection."
                    is IOException -> "Network error. Please check your internet connection."
                    is HttpException -> "Server error: ${e.code()}"
                    else -> e.message ?: "Unknown error occurred"
                }
            )
        }
    }

    suspend fun safeApiCallUnit(apiCall: suspend () -> Response<ApiResponse<Unit>>): NetworkResult<Unit> {
        return try {
            val response = apiCall()
            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    NetworkResult.Success(Unit)
                } else {
                    NetworkResult.Error(
                        message = body?.message ?: "API call failed",
                        code = response.code()
                    )
                }
            } else {
                val errorMessage = parseErrorMessage(response.errorBody()?.string())
                    ?: "HTTP ${response.code()}: ${response.message()}"
                NetworkResult.Error(
                    message = errorMessage,
                    code = response.code()
                )
            }
        } catch (e: Exception) {
            NetworkResult.Error(
                message = when (e) {
                    is SocketTimeoutException -> "Request timeout. Please try again."
                    is IOException -> "Network error. Please check your connection."
                    is HttpException -> "HTTP Error: ${e.code()}"
                    else -> e.message ?: "Unknown error occurred"
                }
            )
        }
    }

    suspend fun safeApiCallMessageOnly(apiCall: suspend () -> Response<ApiResponse<Unit>>): NetworkResult<Unit> {
        return try {
            val response = apiCall()
            if (response.isSuccessful) {
                val body = response.body()
                if (body?.success == true) {
                    NetworkResult.Success(Unit)
                } else {
                    NetworkResult.Error(
                        message = body?.message ?: "API call failed",
                        code = response.code()
                    )
                }
            } else {
                val errorMessage = parseErrorMessage(response.errorBody()?.string())
                    ?: "HTTP ${response.code()}: ${response.message()}"
                NetworkResult.Error(
                    message = errorMessage,
                    code = response.code()
                )
            }
        } catch (e: Exception) {
            NetworkResult.Error(
                message = when (e) {
                    is SocketTimeoutException -> "Request timeout. Please try again."
                    is IOException -> "Network error. Please check your connection."
                    is HttpException -> "HTTP Error: ${e.code()}"
                    else -> e.message ?: "Unknown error occurred"
                }
            )
        }
    }

    private fun parseErrorMessage(errorBody: String?): String? {
        return try {
            if (errorBody != null) {
                // Try to parse JSON error response
                val gson = GsonBuilder()
                    .registerTypeAdapter(Boolean::class.java, BooleanTypeAdapter())
                    .registerTypeAdapter(Boolean::class.javaPrimitiveType, BooleanTypeAdapter())
                    .create()
                val errorResponse = gson.fromJson(errorBody, ApiResponse::class.java)
                errorResponse.message
            } else {
                null
            }
        } catch (e: Exception) {
            errorBody
        }
    }

    fun getErrorMessage(throwable: Throwable): String {
        return when (throwable) {
            is SocketTimeoutException -> "Request timeout. Please try again."
            is IOException -> "Network error. Please check your connection."
            is HttpException -> {
                when (throwable.code()) {
                    401 -> "Authentication failed. Please login again."
                    403 -> "Access denied. You don't have permission."
                    404 -> "Resource not found."
                    500 -> "Server error. Please try again later."
                    else -> "HTTP Error: ${throwable.code()}"
                }
            }
            else -> throwable.message ?: "Unknown error occurred"
        }
    }
}
