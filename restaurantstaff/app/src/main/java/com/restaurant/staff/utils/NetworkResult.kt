package com.restaurant.staff.utils

sealed class NetworkResult<T> {
    data class Success<T>(val data: T) : NetworkResult<T>()
    data class Error<T>(val message: String?) : NetworkResult<T>()
    class Loading<T> : NetworkResult<T>()
}
