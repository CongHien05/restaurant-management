package com.restaurant.staff.data.remote

import com.restaurant.staff.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface AuthApiService {

    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<ApiResponse<LoginResponse>>

    @GET("auth/profile")
    suspend fun getProfile(): Response<ApiResponse<User>>

    @PUT("auth/change-password")
    suspend fun changePassword(@Body request: ChangePasswordRequest): Response<ApiResponse<Unit>>

    @POST("auth/logout")
    suspend fun logout(): Response<ApiResponse<Unit>>

    @GET("auth/validate")
    suspend fun validateToken(): Response<ApiResponse<Map<String, Any>>>
}
