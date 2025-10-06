package com.restaurant.staff.data.repository

import com.restaurant.staff.data.model.*
import com.restaurant.staff.data.remote.AuthApiService
import com.restaurant.staff.data.remote.ApiClient
import com.restaurant.staff.utils.NetworkUtils
import com.restaurant.staff.utils.PreferenceManager
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow

class AuthRepository(
    private val authApiService: AuthApiService,
    private val preferenceManager: PreferenceManager
) {

    fun isLoggedIn(): Boolean {
        return preferenceManager.isLoggedIn()
    }

    fun getCurrentUser(): User? {
        return preferenceManager.getUserInfo()
    }

    suspend fun login(username: String, password: String): Flow<Resource<LoginResponse>> = flow {
        emit(Resource.Loading())

        try {
            val request = LoginRequest(username, password)
            val result = NetworkUtils.safeApiCall { authApiService.login(request) }

            when (result) {
                is NetworkResult.Success -> {
                    val loginResponse = result.data

                    // Save auth data
                    preferenceManager.saveAuthToken(loginResponse.token)
                    preferenceManager.saveUserInfo(loginResponse.user)

                    // Set token in ApiClient for future requests
                    ApiClient.setAuthToken(loginResponse.token)

                    // Save login preferences if needed
                    if (preferenceManager.shouldRememberLogin()) {
                        preferenceManager.saveLastUsername(username)
                    }

                    emit(Resource.Success(loginResponse))
                }
                is NetworkResult.Error -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Login failed"))
        }
    }

    suspend fun logout(): Flow<Resource<Unit>> = flow {
        emit(Resource.Loading())

        try {
            // Call logout API
            val result = NetworkUtils.safeApiCallUnit { authApiService.logout() }

            // Clear local data regardless of API call result
            preferenceManager.logout()
            ApiClient.clearAuthToken()

            when (result) {
                is NetworkResult.Success -> {
                    emit(Resource.Success(Unit))
                }
                is NetworkResult.Error -> {
                    // Still success locally even if API fails
                    emit(Resource.Success(Unit))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Clear local data even if exception occurs
            preferenceManager.logout()
            ApiClient.clearAuthToken()
            emit(Resource.Success(Unit))
        }
    }

    suspend fun getProfile(): Flow<Resource<User>> = flow {
        emit(Resource.Loading())

        try {
            val result = NetworkUtils.safeApiCall { authApiService.getProfile() }

            when (result) {
                is NetworkResult.Success -> {
                    val user = result.data
                    preferenceManager.saveUserInfo(user)
                    emit(Resource.Success(user))
                }
                is NetworkResult.Error -> {
                    // Try to return cached user if API fails
                    val cachedUser = preferenceManager.getUserInfo()
                    if (cachedUser != null) {
                        emit(Resource.Success(cachedUser))
                    } else {
                        emit(Resource.Error(result.message))
                    }
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // Try to return cached user if exception occurs
            val cachedUser = preferenceManager.getUserInfo()
            if (cachedUser != null) {
                emit(Resource.Success(cachedUser))
            } else {
                emit(Resource.Error(e.message ?: "Failed to get profile"))
            }
        }
    }

    suspend fun changePassword(currentPassword: String, newPassword: String): Flow<Resource<Unit>> = flow {
        emit(Resource.Loading())

        try {
            val request = ChangePasswordRequest(currentPassword, newPassword)
            val result = NetworkUtils.safeApiCallUnit { authApiService.changePassword(request) }

            when (result) {
                is NetworkResult.Success -> {
                    emit(Resource.Success(Unit))
                }
                is NetworkResult.Error -> {
                    emit(Resource.Error(result.message))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            emit(Resource.Error(e.message ?: "Failed to change password"))
        }
    }

    suspend fun validateToken(): Flow<Resource<Boolean>> = flow {
        emit(Resource.Loading())

        try {
            // First check if token is expired locally
            if (ApiClient.isTokenExpired()) {
                android.util.Log.w("AuthRepository", "Token expired locally, logging out")
                preferenceManager.logout()
                ApiClient.clearAuthToken()
                emit(Resource.Success(false))
                return@flow
            }

            val result = NetworkUtils.safeApiCall { authApiService.validateToken() }

            when (result) {
                is NetworkResult.Success -> {
                    emit(Resource.Success(true))
                }
                is NetworkResult.Error -> {
                    // Token is invalid, logout user
                    android.util.Log.w("AuthRepository", "Token invalid on server, logging out")
                    preferenceManager.logout()
                    ApiClient.clearAuthToken()
                    emit(Resource.Success(false))
                }
                is NetworkResult.Loading -> {
                    emit(Resource.Loading())
                }
            }
        } catch (e: Exception) {
            // On exception, assume token is invalid
            android.util.Log.w("AuthRepository", "Exception validating token, logging out: ${e.message}")
            preferenceManager.logout()
            ApiClient.clearAuthToken()
            emit(Resource.Success(false))
        }
    }

    // Preference management
    fun setRememberLogin(remember: Boolean) {
        preferenceManager.setRememberLogin(remember)
    }

    fun shouldRememberLogin(): Boolean {
        return preferenceManager.shouldRememberLogin()
    }

    fun getLastUsername(): String? {
        return preferenceManager.getLastUsername()
    }

    fun isFirstRun(): Boolean {
        return preferenceManager.isFirstRun()
    }

    fun setFirstRunComplete() {
        preferenceManager.setFirstRunComplete()
    }

    fun clearAuthToken() {
        preferenceManager.logout()
        ApiClient.clearAuthToken()
    }
}

