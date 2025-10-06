package com.restaurant.staff.data.remote

import com.restaurant.staff.data.model.*
import retrofit2.Response
import retrofit2.http.*

interface MenuApiService {

    @GET("categories")
    suspend fun getCategories(): Response<ApiResponse<List<Category>>>

    @GET("categories/{id}/items")
    suspend fun getItemsByCategory(@Path("id") categoryId: Int): Response<ApiResponse<CategoryWithItems>>

    @GET("menu")
    suspend fun getAllMenuItems(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 20,
        @Query("available_only") availableOnly: Boolean = true
    ): Response<ApiResponse<PaginatedResponse<MenuItem>>>

    @GET("menu/search")
    suspend fun searchMenuItems(
        @Query("q") query: String,
        @Query("category_id") categoryId: Int? = null,
        @Query("available_only") availableOnly: Boolean = true
    ): Response<ApiResponse<MenuSearchResult>>
}
